<?php
/**
 *
 * @author MediaSfera
 * @author Vitaliy Moskalyuk
 *
 */
namespace app\components\mediasfera;

use app\components\Helper;
use DateTime;
use DateTimeZone;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

/**
 *
 * @property NewsPostWrapper $post
 *
 * */

class MediasferaNewsParser
{
    private const DEBUG = false;

    public const TIMEZONE = null;

    public const DATEFORMAT = 'D, d M Y H:i:s O';

    // Skip elements. If element value is true, stop parsing article
    public const ARTICLE_BREAKPOINTS = [
        'name' => [
            'br' => false,
            'hr' => false,
            'style' => false,
            'script' => false,
            'noscript' => false,
            'table' => false,
        ],
        'text' => [],
        'id' => [],
        'class' => [],
    ];


    public static function getBreakpoints() : array
    {
        return array_replace_recursive(self::ARTICLE_BREAKPOINTS, static::ARTICLE_BREAKPOINTS);
    }


    protected static function parseNodes(Crawler $node) : void
    {
        if(static::$post->stopParsing) {
            return;
        }

        $nodes = $node->children();

        if(!count($nodes)) {
            return;
        }

        $nodes->each(function ($node) {
            static::parseNode($node);
        });
    }

    protected static function parseNode(Crawler $node, ?string $filter = null) : void
    {
        if(static::$post->stopParsing) {
            return;
        }

        $node = self::filterNode($node, $filter);

        foreach (static::getBreakpoints() as $key => $array) {

            $values = [];

            switch ($key) {
                case 'name' :
                    $values[] = $node->nodeName();
                    break;
                case 'text' :
                    $values[] = $node->text();
                    break;
                case 'id' :
                case 'class' :
                case 'src' :
                    $values = explode(' ', $node->attr($key));
            }

            if(!$values) {
                continue;
            }

            foreach ($values as $value) {
                if(array_key_exists($value, $array)) {

                    if($array[$value]) {
                        static::$post->stopParsing();
                    }

                    return;
                }
            }
        }

        $nodeName = $node->nodeName();

        switch ($nodeName)
        {
            case 'body' :
            case 'figure' :
            case 'center' :
                $nodes = $node->children();
                if ($nodes->count()) {
                    static::parseNodes($node);
                }
                break;

            case 'h1' :
            case 'h2' :
            case 'h3' :
            case 'h4' :
            case 'h5' :
            case 'h6' :
                static::$post->itemHeader = [
                    $node->text(),
                    substr($nodeName, 1, 1)
                ];
                break;

            case 'img' :
                static::$post->itemImage = static::getNodeImage('src', $node);
                break;

            case 'a' :
                if($node->text()) {
                    static::$post->itemLink = [
                        $node->text(),
                        $node->attr('href')
                    ];
                } else if($node->filter('img')->count()) {
                    static::$post->itemImage = static::getNodeImage('src', $node->filter('img'));
                }
                break;

            case 'iframe' :
            case 'video' :
                static::$post->itemVideo = static::getNodeVideoId($node);
                break;

            case 'blockquote' :
            case 'q' :
                static::$post->itemQuote = $node->text();
                break;

            case 'div' :
                $nodes = $node->children();
                if ($nodes->count()) {
                    static::parseNodes($node);
                } else {
                    static::$post->itemText = $node->text();
                }
                break;

            case 'figcaption' :
            case 'span' :
            case 'strong' :
            case 'p' :
            case 'b' :
                $nodes = $node->children();
                if ($nodes->count()) {
                    static::parseSection($node);
                } else {
                    static::$post->itemText = $node->text();
                }
                break;

            case 'ul' :
            case 'ol' :
                static::parseList($node);
                break;
            default :
                if(self::DEBUG) {
                    throw new \Exception('Unknown tag ' . $nodeName);
                } else {
                    trigger_error('Unknown tag ' . $nodeName, E_USER_WARNING);
                }
        }
    }


    protected static function parseSection(Crawler $node) : void
    {
        $allow_tags = [
            'p',
            'a',
            'img',
            'q',
            'iframe',
        ];

        $html = strip_tags($node->html(), $allow_tags);

        if(!$html) {
            return;
        }

        $_html = '<body><div>' . $html . '</div></body>';

        $node = new Crawler($_html);

        $node->children('body > div > *')->reduce(function (Crawler $node) use (&$html, &$items) {

            $nodeHtml = $node->outerHtml();

            $chunks = explode($nodeHtml, $html, 2);

            $item = trim(array_shift($chunks));

            if($item) {
                static::$post->itemText = $html;
            }

            static::parseNode($node);

            $html = array_shift($chunks);
        });

        $html = trim($html);

        if(strlen($html) > 1) {
            static::$post->itemText = $html;
        }
    }


    protected static function parseList(Crawler $node) : void
    {
        if(!$node->text()) {
            static::parseSection($node);
            return;
        } else if($node->filter('img')->count()) {
            static::parseSection($node);
            return;
        }

        $result = [];

        $node->filter('li')->each(function ($node) use (&$result) {
            $result[] = $node->text();
        });

        $result = array_filter($result);

        if (count($result)) {
            static::$post->itemText = '- ' . implode(PHP_EOL . '- ', $result);
        }
    }


    protected static function filterNode(Crawler $node, ?string $filter) : Crawler
    {
        if(!$filter) {
            return $node;
        }

        if (strpos($filter, "//") === 0) {
            return $node->filterXPath($filter);
        } else {
            return $node->filter($filter);
        }
    }


    protected static function getNodeData(string $data, Crawler $node, ?string $filter = null) : ?string
    {
        $node = static::filterNode($node, $filter);

        if($node->count()) {
            switch ($data)
            {
                case 'text' :
                    return $node->text();
                default :
                    return $node->attr($data);
            }
        }

        return null;
    }


    protected static function getNodeDate(string $data, Crawler $node, ?string $filter = null) : ?string
    {
        $text = static::getNodeData($data, $node, $filter);

        if(!$text) {
            return null;
        }

        $date = static::fixDate($text);

        if($date) {
            return $date;
        } else {
            return $text;
        }
    }


    public static function fixDate(string $date) : ?string
    {
        if(static::TIMEZONE !== null) {
            $dateTime = DateTime::createFromFormat(static::DATEFORMAT, $date, new DateTimeZone(static::TIMEZONE));
        }
        else {
            $dateTime = DateTime::createFromFormat(static::DATEFORMAT, $date);
        }

        if ($dateTime) {
            $dateTime->setTimezone(new DateTimeZone('UTC'));
            return $dateTime->format('Y-m-d H:i:s');
        }

        return null;
    }


    public static function getNodeImage(string $data, Crawler $node, ?string $filter = null) : ?string
    {
        $node = static::filterNode($node, $filter);

        switch ($data)
        {
            case 'style' :
                $style = $node->attr('style');

                if(!$style) {
                    return null;
                }

                $pattern = '/(background-image|background)\s*:\s*url\((?\'img\'[^)]*)\)/';

                preg_match_all($pattern, $style, $matches);

                if(count($matches['img'])) {
                    $src = trim(end($matches['img']), ' \'"');
                } else {
                    $src = null;
                }
                break;
            default :
                $src = $node->attr($data) ?? $node->attr('src') ?? $node->attr('data-src');
        }

        return static::resolveUri($src);
    }

    public static function getNodeVideoId(Crawler $node) : ?string
    {
        switch ($node->nodeName())
        {
            case 'iframe' :
                $src = $node->attr('src');
                break;
            case 'video' :
                $src = $node->filter('source')->first()->attr('src');
                break;
            default :
                $src = null;
                break;
        }

        if(!$src) {
            return null;
        }

        $pattern = '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i';

        if (preg_match($pattern, $src, $match)) {
            return $match[1];
        }

        return null;
    }


    protected static function resolveUri(string $uri): string
    {
        $uri = UriResolver::resolve($uri, static::SITE_URL);

        $uri = urlencode($uri);
        $uri = str_replace(["%3A", "%2F"], [":", "/"], $uri);

        return $uri;
    }


    public static function getPage($url)
    {
        $curl = Helper::getCurl();
        $curl->setOption(CURLOPT_HEADER, true);
        $curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $curl->setOption(CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $content = $curl->get($url);

        $code = $curl->responseCode ?? null;

        if (empty($content) || $code < 200 || $code >= 400) {
            throw new \Exception('Can\'t open url ' . $curl->getUrl());
        }

        return $content;
    }

    public static function getPage2($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        // curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $response = curl_exec($ch);

        if (empty($response))
        {
            throw new \Exception('Can\'t open url ' . $url);
        }

        $curl_info = curl_getinfo($ch);

        // решаем проблемы кодировки
        $c       = '';
        $encot   = false;
        $content_type = $curl_info['content_type'];

        if ($content_type == 'application/rss+xml') {
            preg_match('/encoding=["|\']([-a-z0-9_]+)["|\']/i', $response, $found);

            $p = trim($found[1]);

            if (!empty($p) && $p != "") {
                $c     = $p;
                $encot = true;
            }
        }
        else if (strpos($content_type, 'charset=') !== false) {
            $c     = str_replace("text/html; charset=", "", $content_type);
            $encot = true;
        }
        else {
            preg_match('/charset=([-a-z0-9_]+)/i', $response, $found);

            $p = trim($found[1]);

            if (!empty($p) && $p != "") {
                $c     = $p;
                $encot = true;
            }
        }

        $c = strtolower($c);

        $encot = false;

        if ($encot == true && $c != 'utf-8') {
            $response = mb_convert_encoding($response, 'UTF-8', $c);
        }

        curl_close($ch);

        $header_size = $curl_info['header_size'];
        $resCode     = $curl_info['http_code'];
        $header      = substr($response, 0, $header_size);
        $body        = substr($response, $header_size);

        if ($resCode == '200') {
            return $body;
        }

        throw new \Exception('Can\'t open url ' . $url . '. Code: ' . $resCode);
    }
}
