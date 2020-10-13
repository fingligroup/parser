<?php


namespace app\components\parser\news;


use app\components\mediasfera\MediasferaBaseParser;
use app\components\Helper;
use app\components\parser\NewsPost;
use app\components\parser\NewsPostItem;
use app\components\parser\ParserInterface;
use Symfony\Component\DomCrawler\Crawler;

class SmolnarodParser extends MediasferaBaseParser implements ParserInterface
{
    public const USER_ID = 2;
    public const FEED_ID = 2;

    public const NEWS_LIMIT = 100;

    public const SITE_URL = 'https://smolnarod.ru/';
    public const NEWSLIST_URL = 'https://smolnarod.ru/all-news/';

    public const TIMEZONE = '+0300';
    public const DATEFORMAT = 'd.m.Y H:i';

    public const NEWSLIST_POST = '#section_weekly_news_wrapper .section_russia_news_item';
    public const NEWSLIST_TITLE = '.section_russia_news_item_title_link a';
    public const NEWSLIST_LINK = '.section_russia_news_item_title_link a';
    public const NEWSLIST_DATE = '.news___chrono__item__category_date_time span:last-child';
    public const NEWSLIST_DESC = '.section_russia_news_item_excerpt';
    public const NEWSLIST_IMG = '.img-fluid';

    public const ARTICLE_HEADER = '#single_article_wrapper_inner h1';
    public const ARTICLE_IMG = '.single_article_content_title_photo_wrapper img';
    public const ARTICLE_BODY = '.single_article_content_inner';

    public const ARTICLE_BREAKPOINTS = [
        'text' => [
            'Читать также:' => true,
        ],
        'id' => [
            'add_your_news_alert' => false,
        ],
        'class' => [
            'widget_text' => false,
            'd-none' => false,
            'd-sm-block' => false,
            'd-md-none' => false,
            'd-block' => false,
            'd-sm-none' => false,
            'clear' => false,
            'add_your_news_alert' => false,
            'navhold' => false,
        ]
    ];

    protected static bool $articleStopParse = false;

    public static function run(): array
    {
        $posts = [];
        $curl = Helper::getCurl();

        $listCrawler = new Crawler($curl->get(self::NEWSLIST_URL));

        static::checkResponseCode($curl);

        $listCrawler->filter(self::NEWSLIST_POST)->slice(0, self::NEWS_LIMIT)->each(function (Crawler $node) use (&$posts) {

            $img = static::getImageUri(static::getNodeAttr($node, 'src', self::NEWSLIST_IMG));

            $post = new NewsPost(
                static::class,
                static::getNodeText($node, self::NEWSLIST_TITLE),
                static::getNodeText($node, self::NEWSLIST_DESC),
                static::getNodeDate($node, self::NEWSLIST_DATE),
                static::getNodeAttr($node, 'href', self::NEWSLIST_LINK),
                static::resolveUri($img)
            );

            $curl = Helper::getCurl();
            $articleContent = $curl->get($post->original);

            static::checkResponseCode($curl);

            if (!empty($articleContent)) {

                static::$articleStopParse = false;

                $articleCrawler = new Crawler($articleContent);

                $header = static::getPostItemHeader(
                    static::filterNode($articleCrawler, self::ARTICLE_HEADER),
                    1
                );

                if($header) {
                    $post->addItem($header);
                }

                $image = static::getPostItemImage(
                    static::filterNode($articleCrawler, self::ARTICLE_IMG)
                );

                if($image) {
                    $post->addItem($image);
                }

                $items = array_filter(static::parseNodes($articleCrawler->filter(self::ARTICLE_BODY)));

                foreach ($items as $item) {
                    $post->addItem($item);
                }
            }

            $posts[] = $post;
        });

        return $posts;
    }

    protected static function getImageUri($uri) : string
    {
        $query = parse_url($uri, PHP_URL_QUERY);
        parse_str($query, $params);
        $src = $params['src'] ?? null;

        if($src) {
            return $src;
        }

        return $uri;
    }


    public static function getPostItemImage(Crawler $node, bool $fromStyle = false) : ?NewsPostItem
    {
        $item = parent::getPostItemImage($node, $fromStyle);

        if($item) {
            $item->image = static::getImageUri($item->image);

            return $item;
        }

        return null;
    }
}
