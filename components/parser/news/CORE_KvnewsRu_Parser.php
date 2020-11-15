<?php
/**
 * Стартовый шаблон для разработчика
 *
 * Данный класс предназначается для нужд шаблонизации
 *
 * @author FingliGroup <info@fingli.ru>
 * @author Roman Goncharenya <goncharenya@gmail.com>
 *
 * @note   Данный код предоставлен в рамках оказания услуг, для выполнения поставленных задач по сбору и обработке данных.
 * Переработка, адаптация и модификация ПО без разрешения правообладателя является нарушением исключительных прав.
 *
 */

namespace app\components\parser\news;

use fingli\ParserCore\ParserCore;
use app\components\parser\ParserInterface;
use Symfony\Component\DomCrawler\Crawler;
use yii\base\Exception;

// part 4
class CORE_KvnewsRu_Parser extends ParserCore implements ParserInterface
{
    const USER_ID = 2;
    const FEED_ID = 2;
    // поддерживаемая версия ядра
    // (НЕ ИЗМЕНЯТЬ САМОСТОЯТЕЛЬНО!)
    const FOR_CORE_VERSION = '1.12';
    // дебаг-режим (только для разработки) - выводит информацию о действиях парсера
    protected const DEBUG = 0;

    public function __construct()
    {
        $this->config = [
            // режимы работы парсера:
            // rss - RSS витрина
            // desktop - обычный сайт HTML
            'mode'    => 'rss',

            // максимальное количество новостей, берушихся с витрины
            // (опционально)
            //            'itemsLimit' => 3,

            // настройки сайта
            'site'    => [
                // протокол и домен
                // (обязательный)
                'url'         => 'http://kvnews.ru',

                // использовать юзер-агенты в http запросах.
                // (опционально)
                'user_agent'  => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/42.0',
                //                'user_agent'  => 'bot',

                // часовой пояс UTC.
                // Чтобы определить часовой пояс, нужно зайти на https://time.is/Moscow и выбрать ближайший крупный город к которому относится сайт
                // узнать UTC и прописать его в формате +XX00
                // Например, Москва: '+0300', Владивосток: '+1000'
                // (опционально)
                'time_zone'   => '+0300',

                // формат даты для HTML витрины и карточки
                // (см. https://www.php.net/manual/ru/datetime.format.php)
                // d - день
                // m - месяц
                // Y - полный год
                // y - год, две цифры
                // H - час
                // i - минуты
                'date_format' => 'd.m.Y H:i',

                // формат даты в RSS
                // (указывать только если он отличается от стандартного D, d M Y H:i:s O!)
                //                'date_format_rss' => 'D, d M Y H:i:s O',

                // кодировка сайта (если определяется некорректно)
                //                'encoding'    => 'UTF-8'
            ],

            // настройки витрины (режим RSS)
            'rss'     => [
                // относительный URL где находится RSS
                // (обязательный)
                'url'           => '/structure/rss/all',

                // css селектор для элемента витрины (желательно от корня)
                // (обязательный)
                'element'       => 'rss > channel > item',

                // css селектор для названия элемента (относительно элемента)
                // (обязательный)
                'element-title' => 'title',

                // css селектор для ссылки (относительно элемента)
                // (обязательный)
                'element-link'  => 'link',

                // css селектор для описания элемента (относительно элемента)
                // (заполняется только, если отсутствует в карточке)
                //                'element-description' => 'description',

                // css селектор для картинки элемента (относительно элемента)
                // (заполняется только, если отсутствует в карточке)
                'element-image' => 'enclosure[url]',

                // css селектор для даты элемента (относительно элемента)
                // (заполняется только, если отсутствует в карточке)
                'element-date'  => 'pubDate',
            ],

            // настройка карточки элемента
            // *** в CSS-селекторах можно указывать несколько селекторов через запятую (например, если сайт имеет несколько шаблонов карточки новости)
            'element' => [

                // css-селектор для контейнера карточки
                // (все дальнейшие пути строятся относительно этого контейнера)
                // (обязательный)
                //                'container'           => '.news-page:nth-child(1)',
                //                'container'           => '.-grid-center-wr > .news-page',
                //                'container'           => '.-margin-side:nth-child(2)  .news-page',
                //                'container'           => '.-margin-side:nth-of-type(2)  .news-page',
                //                'container'           => '.news-page:nth-child(2)',
                'container'           => '.contain',

                // css-селектор для основного текста
                // (для заполнения модели NewsPostItem)
                // (обязательный)
                'element-text'        => '.news-page-content',

                // css-селектор для получения даты создания новости
                // (заполняется только, если отсутствует в витрине)
                'element-date'        => '',

                // css селектор для описания элемента (относительно элемента)
                // (заполняется только, если отсутствует в витрине)
                'element-description' => '.news-page-content h3:first-child',

                // css селектор для получения картинки
                // !должен содержать конечный аттрибут src! (например: img.main-image[src])
                // (заполняется только, если отсутствует в витрине)
                'element-image'       => '',

                // css-селектор для цитаты
                // (если не заполнено, то по умолчанию берутся теги: blockquote и q)
                // (опционально)
                'element-quote'       => 'i',

                // игнорируемые css-селекторы
                // (можно через запятую)
                // (опционально)
                'ignore-selectors'    => '.news-page-content h3:first-child',
            ]
        ];

        parent::__construct();
    }

    // @bug: почему-то  текст оказался внутри комментария
    // геттер элементов HTML
    protected
    function getElementsDataFromHtml(string $html, string $containerSelector, string $elementSelector, string $get = 'html'
    )
    : array {
        $fullSelector = trim($containerSelector . ' ' . $elementSelector);

        if (empty($fullSelector))
        {
            throw new Exception('Не установлен CSS-селектор!');
        }

        // решаем проблемы с кодировкой
        if ($this->currentCharset != 'utf-8')
        {
            $html = str_replace('text/html; charset=' . $this->currentCharset, 'text/html; charset=utf-8', $html);
            $html = str_replace('<meta charset="' . $this->currentCharset . '">', '<meta charset="utf-8">', $html);
        }

        $data = [];

        $html = str_replace('<!--div class="ya-site-form ya-site-form_inited_no"', '<div class="ya-site-form ya-site-form_inited_no"', $html);

        $Crawler   = new Crawler($html);
        $attribute = $this->getAttrFromSelector($elementSelector);
        $elements  = $Crawler->filter($fullSelector);

        if ($elements)
        {
            $elements->each(function (Crawler $element, $i) use (&$data, $get, $attribute, $fullSelector) {
                if (!empty($attribute))
                {
                    // если запрашивается style, то ищем ссылку
                    if ($attribute == 'style')
                    {
                        $attrVal = $element->attr($attribute);
                        $data[]  = $this->getUrlFromStyleAttr($attrVal);
                    }
                    else
                    {
                        $data[] = $element->attr($attribute);
                    }
                }
                elseif ($get == 'html')
                {
                    $data[] = $element->outerHtml();
                }
                elseif ($get == 'text')
                {
                    $data[] = $element->text();
                }
            });
        }

        return $data;
    }

    public static function run()
    : array
    {
        $Parser = new self();

        $items = $Parser->getItems();
        $posts = $Parser->getCards(array_keys($items));

        return $posts;
    }
}