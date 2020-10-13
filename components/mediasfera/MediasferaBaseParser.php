<?php

namespace app\components\mediasfera;

use app\components\Helper;
use app\components\parser\NewsPostItem;
use DateTime;
use DateTimeZone;
use linslin\yii2\curl\Curl;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;


class MediasferaBaseParser
{
    public const TIMEZONE = null;

    public const DATEFORMAT = 'D, d M Y H:i:s O';

    // Skip elements. If element value is true, stop parsing article
    public const ARTICLE_BREAKPOINTS = [
        'name' => [
            'br' => false,
            'hr' => false,
            'style' => false,
            'script' => false,
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


    public static function getPostItemHeader(Crawler $node, int $level = 1) : ?NewsPostItem
    {
        $text = Helper::prepareString($node->text());

        if($text) {
            return new NewsPostItem(
                NewsPostItem::TYPE_HEADER,
                $text,
                null,
                null,
                $level
            );
        }

        return null;
    }


    public static function getPostItemText(Crawler $node) : ?NewsPostItem
    {
        $text = $node->text();

        if($text) {
            return new NewsPostItem(
                NewsPostItem::TYPE_TEXT,
                $text
            );
        }

        return null;
    }


    public static function getPostItemImage(Crawler $node, bool $fromStyle = false) : ?NewsPostItem
    {
        if($fromStyle) {

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
        } else {
            $src = $node->attr('src') ?? $node->attr('data-src');
        }

        if($src) {
            return new NewsPostItem(
                NewsPostItem::TYPE_IMAGE,
                null,
                static::resolveUri($src)
            );
        }

        return null;
    }


    public static function getPostItemQuote(Crawler $node) : ?NewsPostItem
    {
        $text = $node->text();

        if($text) {
            return new NewsPostItem(
                NewsPostItem::TYPE_QUOTE,
                $text
            );
        }

        return null;
    }


    public static function getPostItemLink(Crawler $node) : ?NewsPostItem
    {
        $text = $node->text() ?? null;
        $href = $node->attr('href');

        if($href) {
            return new NewsPostItem(
                NewsPostItem::TYPE_LINK,
                $text,
                null,
                $href
            );
        }

        return null;
    }


    public static function getPostItemVideo(Crawler $node) : ?NewsPostItem
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
            $id = $match[1];

            return new NewsPostItem(
                NewsPostItem::TYPE_VIDEO,
                null,
                null,
                null,
                null,
                $id
            );
        }

        return null;
    }


    protected static function parseNodes(Crawler $node) : array
    {
        $nodes = $node->children();

        if(!count($nodes)) {
            return [];
        }

        $items = [];

        $nodes->each(function ($node) use (&$items) {
            array_push($items, ...(array_filter(static::parseNode($node))));
        });

        return $items;
    }

    protected static function parseNode(Crawler $node, ?string $filter = null) : array
    {
        $node = self::filterNode($node, $filter);

        if(static::$articleStopParse) {
            return [];
        }

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
                    $values = explode(' ', $node->attr($key));
            }

            if(!$values) {
                continue;
            }

            foreach ($values as $value) {
                if(array_key_exists($value, $array)) {

                    if($array[$value]) {
                        static::$articleStopParse = true;
                    }

                    return [];
                }
            }
        }

        $items = [];

        $nodeName = $node->nodeName();

        switch ($nodeName)
        {
            case 'body' :
            case 'figure' :
                $nodes = $node->children();
                if ($nodes->count()) {
                    $items = static::parseNodes($node);
                }
                break;

            case 'h1' :
            case 'h2' :
            case 'h3' :
            case 'h4' :
            case 'h5' :
            case 'h6' :
                $level = substr ($nodeName, 1, 1);
                $items[] = static::getPostItemHeader($node, $level);
                break;

            case 'img' :
                $items[] = static::getPostItemImage($node);
                break;

            case 'a' :
                $items[] = static::getPostItemLink($node);
                break;

            case 'iframe' :
            case 'video' :
                $items[] = static::getPostItemVideo($node);
                break;

            case 'blockquote' :
            case 'q' :
                $items[] = static::getPostItemQuote($node);
                break;

            case 'div' :
                $nodes = $node->children();
                if ($nodes->count()) {
                    $items = static::parseNodes($node);
                } else {
                    $items[] = static::getPostItemText($node);
                }
                break;

            case 'figcaption' :
            case 'span' :
            case 'p' :
            case 'b' :
                $nodes = $node->children();
                if ($nodes->count()) {
                    $items = static::parseSection($node);
                } else {
                    $items[] = static::getPostItemText($node);
                }
                break;

            case 'ul' :
            case 'ol' :
                $items = static::parseList($node);
                break;
            default :
                throw new \Exception('Unknown tag ' . $nodeName);

        }

        return $items;
    }


    protected static function parseSection(Crawler $node) : array
    {
        $allow_tags = [
            'p',
            'a',
            'img',
            'q',
            'iframe',
        ];

        $items = [];

        $html = strip_tags($node->html(), $allow_tags);

        if(!$html) {
            //var_dump('Empty node HTML' . $node->nodeName() . 'original HTML');
            return [];
        }

        $node = new Crawler('<body><div>' . $html . '</div></body>');

        $node->children('body > div > *')->reduce(function (Crawler $node) use (&$html, &$items) {

            $nodeHtml = $node->outerHtml();

            $chunks = explode($nodeHtml, $html, 2);

            $item = trim(array_shift($chunks));

            if($item) {
                $items[] = new NewsPostItem(
                    NewsPostItem::TYPE_TEXT,
                    $item
                );
            }

            array_push($items, ...(static::parseNode($node)));

            $html = array_shift($chunks);
        });

        $html = trim($html);

        if(strlen($html) > 1) {
            $items[] = new NewsPostItem(
                NewsPostItem::TYPE_TEXT,
                $html
            );
        }

        return $items;
    }


    protected static function parseList(Crawler $node) : array
    {
        $result = [];

        $node->filter('li')->each(function ($node) use (&$result) {
            $result[] = '- ' . $node->text();
        });

        if (count($result)) {
            return [
                new NewsPostItem(
                    NewsPostItem::TYPE_TEXT,
                    implode(PHP_EOL, $result)
                )
            ];
        } else {
            return [];
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


    protected static function getNodeText(Crawler $node, ?string $filter = null) : ?string
    {
        $node = static::filterNode($node, $filter);

        if($node) {
            return $node->text();
        }

        return null;
    }


    protected static function getNodeAttr(Crawler $node, string $attr, ?string $filter = null) : ?string
    {
        $node = static::filterNode($node, $filter);

        if($node) {
            return $node->attr($attr);
        }

        return null;
    }


    protected static function getNodeDate(Crawler $node, ?string $filter = null) : ?string
    {
        $text = static::getNodeText($node, $filter);

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


    protected static function checkResponseCode(Curl $curl) : void
    {
        $code = $curl->responseCode ?? null;

        if ($code < 200 || $code >= 400) {
            throw new \Exception('Can\'t open url ' . $curl->getUrl());
        }
    }


    protected static function resolveUri(string $uri): string
    {
        $uri = UriResolver::resolve($uri, static::SITE_URL);

        $uri = urlencode($uri);
        $uri = str_replace(["%3A", "%2F"], [":", "/"], $uri);

        return $uri;
    }
}
