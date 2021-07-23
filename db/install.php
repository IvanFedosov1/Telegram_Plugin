<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Установитm процессор сообщений Telegram.
 */
function xmldb_message_telegram_install() {
    global $DB;
    $result = true;
    $provider = new stdClass();
    $provider->name  = 'telegram';
    $DB->insert_record('message_processors', $provider);
    return $result;
}