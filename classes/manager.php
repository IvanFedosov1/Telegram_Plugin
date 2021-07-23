<?php

namespace message_telegram;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/lib/filelib.php');


 // Класс помощник менеджера Telegram
 
class manager {

    /**
     * @var $secretprefix Переменная, используемая для определения того, что идентификатор chatid не был установлен для пользователя.
     */
    private $secretprefix = 'usersecret::';

    /**
     * @var $curl Объект curl, используемый в этом запуске. Позволяет избежать непрерывного создания объекта curl.
     */
    private $curl = null;

    /**
     * Конструктор. Загружает все необходимые данные.
     */
    public function __construct() {
        $this->config = get_config('message_telegram');
    }

    /**
     * Отправляет сообщение в Telegram.
     * @param string $message Сообщение которое нужно отправить.
     * @param int $userid Идентификатор пользователя Moodle, которому отправляется сообщение.
     */
    public function send_message($message, $userid) {

        if (empty($this->config('sitebottoken'))) {
            return true;
        } else if (empty($chatid = get_user_preferences('message_processor_telegram_chatid', '', $userid))) {
            return true;
        }

        $response = $this->send_api_command('sendMessage', ['chat_id' => $chatid, 'text' => $message]);
        return (!empty($response) && isset($response->ok) && ($response->ok == true));
    }

    /**
     * Установить элемент конфигурации в указанное значение, в объекте и базе данных.
     * @param string $name Имя элемента конфигурации.
     * @param string $value Значение элемента конфигурации.
     */
    public function set_config($name, $value) {
        set_config($name, $value, 'message_telegram');
        $this->config->{$name} = $value;
    }
    /**
     * Возвращает запрашиваемый элемент конфигурации или null. Должен быть загружен в конструкторе.
     * @param string $configitem Запрашиваемый элемент конфигурации.
     * @return mixed Запрашиваемое значение или null.
     */
    public function config($configitem) {
        return isset($this->config->{$configitem}) ? $this->config->{$configitem} : null;
    }

    /**
     * Возвращает HTML для формы предпочтений пользователя.
     * @param array $preferences Массив предпочтений пользователя.
     * @param int $userid Moodle id пользователя, о котором идет речь.
     * @return string HTML для формы.
     */
    public function config_form ($preferences, $userid) {
        // Если chatid не установлен, отобразить ссылку, чтобы сделать это.
        if (!$this->is_chatid_set($userid, $preferences)) {
            // Временно установить chatid пользователя в значение sesskey для безопасности.
            $this->set_usersecret($userid);
            $url = 'https://telegram.me/'.$this->config('sitebotusername').'?start='.$this->usersecret();
            $configbutton = get_string('connectinstructions', 'message_telegram', $this->config('sitebotname'));
            $configbutton .= '<div align="center"><a href="'.$url.'" target="_blank">'.
                get_string('connectme', 'message_telegram') . '</a></div>';
        } else {
            $url = new \moodle_url($this->redirect_uri(), ['action' => 'removechatid', 'userid' => $userid,
                'sesskey' => sesskey()]);
            $configbutton = '<a href="'.$url.'">' . get_string('removetelegram', 'message_telegram') . '</a>';
        }

        return $configbutton;
    }

    /**
     * Создайте переменную, используемую только плагином для обеспечения идентификации пользователя.
     * @return string Созданная переменная для этого пользователя (sesskey Moodle).
     */
    public function usersecret() {
        return sesskey();
    }

    /**
     * Установить идентификатор чата пользователя в usersecret, чтобы обеспечить безопасную идентификацию идентификатора чата.
     * @param int $userid Идентификатор пользователя, для которого нужно установить этот параметр.
     * @return boolean Успех или неудача.
     */
    private function set_usersecret($userid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        if ($userid != $USER->id) {
            require_capability('moodle/site:config', \context_system::instance());
        }

        return set_user_preference('message_processor_telegram_chatid', $this->secretprefix . $this->usersecret(), $userid);
    }

    /**
     * Проверяем, что полученный usersecret совпадает с usersecret пользователя, хранящимся в базе данных.
     * @param string $receivedsecret secret тестирования против хранимых.
     * @param int $userid Идентификатор пользователя, для которого нужно установить этот параметр.
     * @return boolean Успех или неудача
     */
    private function usersecret_match($receivedsecret, $userid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        if ($userid != $USER->id) {
            require_capability('moodle/site:config', \context_system::instance());
        }

        $usersecret = substr(get_user_preferences('message_processor_telegram_chatid', '', $userid), strlen($this->secretprefix));
        return ($usersecret === $receivedsecret);
    }

    /**
     * Убеждаемся, что у пользователя установлен идентификатор чата.
     * @param int $userid Идентификатор пользователя, которого нужно проверить.
     * @param object $preferences Содержит пользовательские настройки Telegram для данного пользователя, если они есть.
     * @return boolean True если идентификатор установлен.
     */
    public function is_chatid_set($userid, $preferences = null) {
        if ($preferences === null) {
            $preferences = new \stdClass();
        }
        if (!isset($preferences->telegram_chatid)) {
            $preferences->telegram_chatid = get_user_preferences('message_processor_telegram_chatid', '', $userid);
        }
        return (!empty($preferences->telegram_chatid) && (strpos($preferences->telegram_chatid, $this->secretprefix) !== 0));
    }

    /**
     * Возвращает URI перенаправления для обработки обратного вызова для OAuth.
     * @return string URI.
     */
    public function redirect_uri() {
        global $CFG;

        return $CFG->wwwroot.'/message/output/telegram/telegramconnect.php';
    }

    /**
     * Получив действительный токен бота, получите имя и имя пользователя бота.
     */
    public function update_bot_info() {
        if (empty($this->config('sitebottoken'))) {
            return false;
        } else {
            $response = $this->send_api_command('getMe');
            if ($response->ok) {
                $this->set_config('sitebotname', $response->result->first_name);
                $this->set_config('sitebotusername', $response->result->username);
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Получаем последнюю информацию от бота и узнаём, инициировал ли пользователь подключение.
     * Необходим только в том случае, если не был создан webHook.
     * @param int $userid Идентификатор пользователя, о котором идет речь.
     * @return boolean 
     */
    public function set_chatid($userid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        if (empty($this->config('sitebottoken'))) {
            return false;
        } else {
            $results = $this->get_updates();
            if ($results !== false) {
                foreach ($results as $object) {
                    if (isset($object->message)) {
                        if ($this->usersecret_match(substr($object->message->text, strlen('/start ')))) {
                            set_user_preference('message_processor_telegram_chatid', $object->message->chat->id, $userid);
                            break;
                        }
                    }
                }
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Удалить идентификатор чата Telegram пользователя из предпочтений.
     * @param int $userid Идентификатор, который необходимо очистить.
     * @return string Информационное сообщение.
     */
    public function remove_chatid($userid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        } else if ($userid != $USER->id) {
            require_capability('moodle/site:config', \context_system::instance());
        }
        unset_user_preference('message_processor_telegram_chatid', $userid);

        return '';
    }

    /**
     * Установить веб-хук для этого сайта в Telegram Bot.
     * @return string Пустой в случае успеха, иначе - сообщение об ошибке.
     */
    public function set_webhook() {
        return 'This feature is still under development... Stand by.';
        if (empty($this->config('sitebottoken'))) {
            $message = get_string('sitebottokennotsetup', 'message_telegram');
        } else {
            $response = $this->send_api_command('setWebhook', ['url' => $this->redirect_uri(), 'allowed_updates' => 'message']);
            if (!empty($response) && isset($response->ok) && ($response->ok == true)) {
                $this->set_config('webhook', '1');
                $message = '';
            } else if (!empty($response) && isset($response->error_code) && isset($response->description)) {
                $message = $response->description;
            }
        }
        return $message;
    }

    /**
     * Возвращает результаты запроса API getUpdates.
     * @return object Объект результатов, декодированный в формате JSON.
     */
    public function get_updates() {
        $response = $this->send_api_command('getUpdates');
        if ($response->ok) {
            return $response->result;
        } else {
            return false;
        }
    }

    /**
     * Отправить команду Telegram API и верните результаты.
     * @param string $command Команда API для отправки.
     * @param array $params Параметры для отправки в команду API. Может быть опущен.
     * @return object Декодированный в JSON объект возврата.
     */
    private function send_api_command($command, $params = null) {
        if (empty($this->config('sitebottoken'))) {
            return false;
        }

        if ($this->curl === null) {
            $this->curl = new \curl();
        }

        return json_decode($this->curl->get('https://api.telegram.org/bot'.$this->config('sitebottoken').'/'.$command, $params));
    }
}