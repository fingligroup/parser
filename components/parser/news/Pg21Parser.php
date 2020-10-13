<?php


namespace app\components\parser\news;


use app\components\mediasfera\MediasferaBaseParser;
use app\components\Helper;
use app\components\parser\NewsPost;
use app\components\parser\NewsPostItem;
use app\components\parser\ParserInterface;
use Symfony\Component\DomCrawler\Crawler;

class Pg21Parser extends MediasferaBaseParser implements ParserInterface
{
    public const USER_ID = 2;
    public const FEED_ID = 2;

    public const NEWS_LIMIT = 100;

    public const SITE_URL = 'https://pg21.ru';
    public const NEWSLIST_URL = 'https://pg21.ru/rss';

    //    public const TIMEZONE = '+0000';
    public const DATEFORMAT = 'D, d M Y H:i:s O';

    public const NEWSLIST_POST = '//rss/channel/item';
    public const NEWSLIST_TITLE = '//title';
    public const NEWSLIST_LINK = '//link';
    public const NEWSLIST_DATE = '//pubDate';
    public const NEWSLIST_DESC = '//description';
    public const NEWSLIST_IMG = '//enclosure';
    public const NEWSLIST_CONTENT = '//yandex:full-text';


    protected static bool $articleStopParse = false;


    public static function run(): array
    {
        $posts = [];
        $curl = Helper::getCurl();

        $listCrawler = new Crawler($curl->get(self::NEWSLIST_URL));

        static::checkResponseCode($curl);

        $listCrawler->filterXPath(self::NEWSLIST_POST)->slice(0, self::NEWS_LIMIT)->each(function (Crawler $node) use (&$posts) {

            $title = static::getNodeText($node, self::NEWSLIST_TITLE);
            $desc = static::getNodeText($node, self::NEWSLIST_DESC);
            $date = static::getNodeDate($node, self::NEWSLIST_DATE);
            $link = static::getNodeText($node, self::NEWSLIST_LINK);
            $img = static::getNodeAttr($node, 'url', self::NEWSLIST_IMG);

            $html = static::filterNode($node, self::NEWSLIST_CONTENT)->html();

            $articleCrawler = new Crawler($html);

            $items = array_filter(static::parseNodes($articleCrawler));

            foreach ($items as $item) {
                if(!$desc && $item->type == NewsPostItem::TYPE_TEXT) {
                    $desc = $item->text;
                }
                if(!$img && $item->type == NewsPostItem::TYPE_IMAGE) {
                    $img = $item->image;
                }
            }

            $post = new NewsPost(
                static::class,
                $title,
                $desc,
                $date,
                $link,
                $img
            );

            foreach ($items as $item) {
                $post->addItem($item);
            }

            $posts[] = $post;
        });

        return $posts;
    }
}
