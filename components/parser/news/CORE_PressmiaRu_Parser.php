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

// part 4
class CORE_PressmiaRu_Parser extends ParserCore implements ParserInterface
{
    const USER_ID = 2;
    const FEED_ID = 2;
    // поддерживаемая версия ядра
    // (НЕ ИЗМЕНЯТЬ САМОСТОЯТЕЛЬНО!)
    const FOR_CORE_VERSION = '1.8';
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
            //            'itemsLimit' => 1,

            // настройки сайта
            'site'    => [
                // протокол и домен
                // (обязательный)
                'url'         => 'http://pressmia.ru',

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
                //                'date_format' => 'd.m.Y H:i',
                'date_format' => 'Y-m-d H:i:s',

                // формат даты в RSS
                // (указывать только если он отличается от стандартного D, d M Y H:i:s O!)
                //                'date_format_rss' => 'D, d M Y H:i:s O',

                // пауза между запросами в секундах (включается только, если сайт начинает блокировку)
                //                'pause'       => 0,

                // заменяем переводы строк пробелами (есть сайты, где не ставят пробелы после перевода строки)
                // 'transform_new_line_to_space' => true
            ],

            // настройки витрины (режим HTML)
            // !!! заполняется, только при отсутствии витрины RSS !!!
            'list'    => [
                // URL где находится витрина
                // (обязательный)
                'url'                 => '/pressclub/',

                // css селектор для контейнера витрины
                // (обязательный)
                'container'           => '.rubric-list-main',

                // css селектор для элемента витрины (относительно контейнера)
                // (обязательный)
                'element'             => '.rubric-list-main-article',

                // ** дальнейшие css-селекторы указываются относительно element

                // css селектор для ссылки на элемент !должен содержать конечный аттрибут href!
                // (обязательный + должен быть обязательный атрибут, где хранится ссылка)
                'element-link'        => '.rubric-list-main-article__announce-title a[href]',

                // css селектор для названия элемента
                // (опционально)
                'element-title'       => '.rubric-list-main-article__announce-title',

                // css селектор для описания элемента
                // (опционально)
                'element-description' => '',

                // css селектор !должен содержать конечный аттрибут src! для картинки элемента
                // (опционально)
                'element-image'       => '',

                // css селектор для даты элемента
                // (опционально)
                //                'element-date'        => '.rubric-list-main-article__announce-descr .article-type-date[datetime]',
            ],

            // настройка карточки элемента
            // *** в CSS-селекторах можно указывать несколько селекторов через запятую (например, если сайт имеет несколько шаблонов карточки новости). Селекторы должны быть уникальны, иначе возможны коллизии
            'element' => [

                // css-селектор для контейнера карточки
                // (можно несколько через запятую, если есть разные шаблоны новости)
                // (обязательный)
                'container'           => 'section.content',

                // ** дальнейшие css-селекторы указываются относительно container

                // css-селектор для основного текста * - данные внутри (картинки, ссылки) парсятся автоматически
                // (можно несколько через запятую, если есть разные шаблоны новости)
                // (обязательный)
                'element-text'        => 'article.article',

                // css-селектор даты создания новости
                // (опционально)
                'element-date'        => '.article__head-block .article-type-date',

                // css селектор для описания элемента
                // (опционально)
                'element-description' => '',

                // css селектор для получения картинки
                // !должен содержать конечный аттрибут src! (например: img.main-image[src])
                // (опционально)
                'element-image'       => '.b-article__media .article_illustration img[src]',

                // css-селектор для цитаты
                // (если не заполнено, то по умолчанию берутся теги: blockquote и q)
                // (опционально)
                'element-quote'       => '',

                // игнорируемые css-селекторы (будут вырезаться из результата)
                // (можно несколько через запятую)
                // (опционально)
                'ignore-selectors'    => '.article .article_head, .article .social-likes, .mfp-hide',

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

    protected function stripTags(string $html, array $allowedTags = [])
    : string {
        // удаляем все переводы строк, чтобы поставить свои
        $html = str_replace("\n", '', $html);
        $html = str_replace("\r", '', $html);

        $html = str_replace('<br><br>', "\n\n ", $html);


        // прежде, чем вырезать теги, нужно компенсировать между ними пробелы
        // и в случае разбиения на параграфы добавить
        $html = str_replace('</div>', ' </div>', $html);
        $html = str_replace('</p>', " </p>\n\n", $html);
        //        $html = str_replace('<br>', " <br>\n", $html);
        //        $html = str_replace('<br/>', " <br/>\n", $html);
        $html = str_replace('</li>', ' </li>', $html);
        $html = str_replace('</td>', ' </td>', $html);

        // а также заменить переводы строк пробелами, если указано в настройках
        // для сайтов, которые не ставят пробелы после перевода строки в тексте
        if (!empty($this->config['site']['transform_new_line_to_space']) && $this->config['site']['transform_new_line_to_space'] === true)
        {
            $html = str_replace("\r", ' ', $html);
            $html = str_replace("\n", ' ', $html);
        }

        $stripped = strip_tags($html, $allowedTags);

        // сжимаем много пробелов в один
        $stripped = preg_replace("/[ ]{2,}/", " ", $stripped);

        return $stripped;
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