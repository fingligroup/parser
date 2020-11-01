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

// part 3 approved by rmn
class CORE_BootlegerRu_Parser extends ParserCore implements ParserInterface
{
    const USER_ID = 2;
    const FEED_ID = 2;
    // поддерживаемая версия ядра
    // (НЕ ИЗМЕНЯТЬ САМОСТОЯТЕЛЬНО!)
    const FOR_CORE_VERSION = '1.0';
    // дебаг-режим (только для разработки) - выводит информацию о действиях парсера
    // 0 - отключен
    // 1 - включен
    // 2 - включен (очень подробный режим)
    // 3 - режим "зануда"
    protected const DEBUG = 0;

    public function __construct()
    {
        $this->config = [
            // режимы работы парсера:
            // rss - RSS витрина
            // desktop - обычный сайт HTML
            'mode'    => 'desktop',

            // максимальное количество новостей, берушихся с витрины
            // ИСПОЛЬЗУЕТСЯ ТОЛЬКО В РЕЖИМЕ DEBUG
            // в остальных случаях жестко задается ядром
            //
            // не забывайте отключать лимит при сдаче парсера!
            //            'itemsLimit' => 2,

            // настройки сайта
            'site'    => [
                // протокол и домен
                // (обязательный)
                'url'         => 'https://bootleger.ru',

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

                // пауза между запросами в секундах (включается только, если сайт начинает блокировку)
                //                'pause'       => 0,

                // заменяем переводы строк пробелами (есть сайты, где не ставят пробелы после перевода строки)
                // 'transform_new_line_to_space' => true
            ],

            // работает, но только 4 новости
            // настройки витрины (режим RSS)
            'rss'     => [
                // относительный URL где находится RSS
                // (обязательный)
                'url'                 => '/feed',

                // css селектор для элемента витрины (желательно от корня)
                // (обязательный)
                'element'             => 'rss > channel > item',

                // ** дальнейшие css-селекторы указываются относительно element

                // css селектор для названия элемента
                // (обязательный)
                'element-title'       => 'title',

                // css селектор для ссылки
                // (обязательный)
                'element-link'        => 'link',

                // css селектор для описания элемента
                // (опционально)
                'element-description' => 'description',

                // css селектор для картинки элемента
                // (опционально)
                'element-image'       => 'enclosure[url]',

                // css селектор для даты элемента
                // (опционально)
                'element-date'        => 'pubDate',
            ],


            // настройки витрины (режим HTML)
            // !!! заполняется, только при отсутствии витрины RSS !!!
            'list'    => [
                // URL где находится витрина
                // (обязательный)
                'url'                 => '/category/novosti',

                // URL для навигации по страницам
                // вместо $page - подставляется номер страницы
                // например: /category/novosti/page/$page
                // (опциональный)
                'url-page'            => '/category/novosti/page/$page',

                // css селектор для контейнера витрины
                // (обязательный)
                'container'           => '#main .post-wrap',

                // css селектор для элемента витрины (относительно контейнера)
                // (обязательный)
                'element'             => '.post-col',

                // ** дальнейшие css-селекторы указываются относительно element

                // css селектор для ссылки на элемент !должен содержать конечный аттрибут href!
                // (обязательный + должен быть обязательный атрибут, где хранится ссылка)
                'element-link'        => '.entry-header .entry-title a[href]',

                // css селектор для названия элемента
                // (опционально)
                'element-title'       => '.entry-header .entry-title',

                // css селектор для описания элемента
                // (опционально)
                'element-description' => '',

                // css селектор !должен содержать конечный аттрибут src! для картинки элемента
                // (опционально)
                'element-image'       => '',

                // css селектор для даты элемента
                // (опционально)
                'element-date'        => '.entry-meta .date',
            ],

            // настройка карточки элемента
            // *** в CSS-селекторах можно указывать несколько селекторов через запятую (например, если сайт имеет несколько шаблонов карточки новости). Селекторы должны быть уникальны, иначе возможны коллизии
            'element' => [

                // css-селектор для контейнера карточки
                // (можно несколько через запятую, если есть разные шаблоны новости)
                // (обязательный)
                'container'           => '#main .post',

                // ** дальнейшие css-селекторы указываются относительно container

                // css-селектор для основного текста * - данные внутри (картинки, ссылки) парсятся автоматически
                // (можно несколько через запятую, если есть разные шаблоны новости)
                // (обязательный)
                'element-text'        => '.entry-content',

                // css-селектор даты создания новости
                // (опционально)
                'element-date'        => '',

                // css селектор для описания элемента
                // (опционально)
                'element-description' => '',

                // css селектор для получения картинки
                // !должен содержать конечный аттрибут src! (например: img.main-image[src])
                // (опционально)
                'element-image'       => '.post-img[style]',

                // css-селектор для цитаты
                // (если не заполнено, то по умолчанию берутся теги: blockquote и q)
                // (опционально)
                'element-quote'       => '',

                // игнорируемые css-селекторы (будут вырезаться из результата)
                // (можно несколько через запятую)
                // (опционально)
                'ignore-selectors'    => '.addtoany_share_save_container, footer.entry-meta',

                // css-селекторы которые будут вставлятся в начало текста новости element-text (селекторы ищутся от корня, т.е. не зависят от container)
                // (опционально)
                'element-text-before' => '',

                // css-селекторы которые будут вставлятся в конец текста новости element-text (селекторы ищутся от корня, т.е. не зависят от container)
                // (опционально)
                'element-text-after'  => '',
            ]
        ];

        parent::__construct();
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