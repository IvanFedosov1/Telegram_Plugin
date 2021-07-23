<?php

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot.'/lib/filelib.php');

$action = optional_param('action', 'setwebhook', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/message/output/telegram/telegramconnect.php'));
$PAGE->set_context(context_system::instance());

require_login();

$telegrammanager = new message_telegram\manager();

if ($action == 'setwebhook') {
    require_sesskey();
    require_capability('moodle/site:config', context_system::instance());
    if (strpos($CFG->wwwroot, 'https:') !== 0) {
        $message = get_string('requirehttps', 'message_telegram');
    } else {
        if (empty(get_config('message_telegram', 'webhook'))) {
            $message = $telegrammanager->set_webhook();
        }
    }
    redirect(new moodle_url('/admin/settings.php', ['section' => 'messagesettingtelegram']), $message);

} else if ($action == 'removechatid') {
    require_sesskey();
    $userid = optional_param('userid', 0, PARAM_INT);
    if ($userid != 0) {
        $message = $telegrammanager->remove_chatid($userid);
    }
    redirect(new moodle_url('/message/notificationpreferences.php', ['userid' => $userid]), $message);
}
