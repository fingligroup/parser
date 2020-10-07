<?php
namespace app\components\helper;

use app\components\parser\NewsPost;
use app\components\parser\NewsPostItem;
use DateTime;
use DateTimeZone;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Набор базовых методов
 */
abstract class TyRunBaseParser
{
    abstract protected static function parseNode(Crawler $node, NewsPost $newPost, int $maxDepth, bool &$stopParsing);

    /**
     * Парсер для тегов <ul>, <ol> и т.п.
     * Разбирает списки в текст с переносом строки
     * @param Crawler $node
     * @param NewsPost $newPost
     */
    protected static function parseUl(Crawler $node, NewsPost $newPost): void
    {
        $parsedUl = '';
        $node->filter('li')->each(function ($node) use (&$parsedUl) {
            $parsedUl .= static::UL_PREFIX . $node->text() . PHP_EOL;
        });
        if (!empty($parsedUl)) {
            $newPost->addItem(
                new NewsPostItem(
                    NewsPostItem::TYPE_TEXT,
                    $parsedUl,
                    null,
                    null,
                    null,
                    null
                ));
        }
    }

    /**
     * Добавляет элемент "видео" в статью
     * @param string $videoId
     * @param NewsPost $newPost
     */
    protected static function addVideo(string $videoId, NewsPost $newPost): void
    {
        if ($videoId) {
            $newPost->addItem(
                new NewsPostItem(
                    NewsPostItem::TYPE_VIDEO,
                    null,
                    null,
                    null,
                    null,
                    $videoId
                ));
        }
    }

    /**
     * Парсер для тегов <img>
     * @param Crawler $node
     * @param NewsPost $newPost
     */
    protected static function parseImage(Crawler $node, NewsPost $newPost): void
    {
        $newPost->addItem(
            new NewsPostItem(
                NewsPostItem::TYPE_IMAGE,
                null,
                $node->attr('src'),
                null,
                null,
                null
            ));
    }

    /**
     * Возвращает id видео на youtube из url, если он есть
     * @param string $str
     * @return string|null
     */
    protected static function extractYouTubeId(string $str): ?string
    {
        /**
         * @see https://stackoverflow.com/questions/2936467/parse-youtube-video-id-using-preg-match
         */
        $pattern = '/(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})/i';
        if (preg_match($pattern, $str, $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * Приводим дату к UTC +0
     * @param string $date
     * @param string $format
     * @return string
     */
    protected static function stringToDateTime(string $date, string $format = 'D, d M Y H:i:s O'): string
    {
        $dateTime = DateTime::createFromFormat($format, $date);
        if (is_a($dateTime, DateTime::class)) {
            $tz = new DateTimeZone('UTC');
            $dateTime->setTimezone($tz);
            return $dateTime->format('d-m-Y H:i:s');
        }
        return $date;
    }
}