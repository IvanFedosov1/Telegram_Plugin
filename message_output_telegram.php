<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/message/output/lib.php');
require_once($CFG->dirroot.'/lib/filelib.php');


class message_output_telegram extends message_output {

    
    public function __construct() {
        $this->manager = new message_telegram\manager();
    }

    /**
     * Обрабатывает сообщение и отправляет уведомление через telegram
     *
     * @param stdClass $eventdata данные о событии, отправленные отправителем сообщения

     * @return true если ok, false если error
     */
    public function send_message($eventdata) {
        global $CFG;

        // Пропустить любые сообщения от приостановленных и удаленных пользователей.
        if (($eventdata->userto->auth === 'nologin') || $eventdata->userto->suspended || $eventdata->userto->deleted) {
            return true;
        }

        if (!empty($CFG->noemailever)) {
            
            debugging('$CFG->noemailever is active, no telegram message sent.', DEBUG_MINIMAL);
            return true;
        }

        return $this->manager->send_message($eventdata->fullmessage, $eventdata->userto->id);
    }

    /**
     * Создает необходимые поля в форме конфигурации обмена сообщениями.
     *
     * @param array $preferences Объект пользовательских предпочтений 
     */
    public function config_form($preferences) {
        global $USER;

        // Если Telegram не был настроен, ничего не делать.
        if (!$this->is_system_configured()) {
            return get_string('notconfigured', 'message_telegram');
        } else {
            return $this->manager->config_form($preferences, $USER->id);
        }
    }

    /**
     * Анализирует отправленные данные формы и сохраняет их в массиве настроек.
     *
     * @param stdClass $form класс формы предпочтений
     * @param array $preferences массив предпочтений
     */
    public function process_form($form, &$preferences) {
        return $this->manager->set_chatid();
    }

    /**
     * Загружает данные конфигурации из базы данных, чтобы поместить их в форму во время первоначального отображения формы.
     *
     * @param object $preferences объект предпочтений
     * @param int $userid id пользователя
     */
    public function load_data(&$preferences, $userid) {
        $preferences->telegram_chatid = get_user_preferences('message_processor_telegram_chatid', '', $userid);
    }

    /**
     * Проверяет, был ли настроен Telegram на уровне пользователя
     * @param  object $user по умолчанию имеет значение $USER.
     * @return bool сделаны ли все настройки у пользователя 
     * в его профиле, что бы использовать этот плагин.
     */
    public function is_user_configured($user = null) {
        global $USER;

        if ($user === null) {
            $user = $USER;
        }
        return ($this->manager->is_chatid_set($user->id));
    }

    /**
     * Проверяет, были ли настроены параметры Telegram
     * @return boolean true если Telegram настроен
     */
    public function is_system_configured() {
        return (!empty(get_config('message_telegram', 'sitebotname')) && !empty(get_config('message_telegram', 'sitebottoken')));
    }
}