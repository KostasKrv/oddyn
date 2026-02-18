<?php

namespace kk\OddsMaster;

use DateTime;
use Exception;
use \GuzzleHttp\Client;

global $OddsMasterInstance;

class OddsMaster
{
    const KINO_CODE = 1100;
    const BASE_API_URL = 'https://api.the-odds-api.com/v4/sports';

    const LIMIT_FOR_LESS = 3;
    const DRAW_IS_CLEAN_LIMIT = 2;


    const _get_key_cronjob = 'cron';

    const _api_set_sport = 'upcoming';
    const _api_set_regions = 'us,eu';
    const _api_set_markets = 'h2h,spreads,totals';
    const _api_set_oddsFormat = 'decimal';
    const _api_set_dateFormat = 'iso';

    const _get_key_snooze_sms_for_draw = 'snooze_draw';
    const _set_warn_after_x_draws_occured = 'warn_after_x_draws_occured';
    const _set_snooze_until_draw = 'snooze_until_draw';
    const _set_warn_on_all_draws = 'warn_on_all_draws';
    const _set_read_until_draw = 'read_until_draw';
    const _set_last_SMS_suggestion_sent = 'last_sms_suggestion_sent';
    const _set_last_PUSH_suggestion_sent = 'last_push_suggestion_sent';
    const _set_toggle_modes = 'toggle_modes';
    const _set_current_toggle_mode = 'current_toggle_mode';
    const _set_draw_watch = 'first_rounds_to_watch';
    const _set_active_hours = 'active_hours';
    const _toggle_modes = ['', 'semi-expanded', 'expanded'];

    const _auth_cookie = 'auth_cookie';

    static function ODDS_API_SECRET_KEY()
    {
        return getenv('ODDS_API_SECRET_KEY');
    }

    static function PUSH_NOTIFICATION_WEBKEY()
    {
        return getenv('PUSH_NOTIFICATION_WEBKEY');
    }

    static function PUSH_NOTIFICATION_ACCESS_TOKEN()
    {
        return getenv('PUSH_NOTIFICATION_ACCESS_TOKEN');
    }

    const DEFAULT_SETTINGS = [
        OddsMaster::_set_warn_after_x_draws_occured => 24,
        OddsMaster::_set_snooze_until_draw => null,
        OddsMaster::_set_read_until_draw => null,
        OddsMaster::_set_last_SMS_suggestion_sent => null,
        OddsMaster::_set_last_PUSH_suggestion_sent => null,
        OddsMaster::_set_toggle_modes => OddsMaster::_toggle_modes,
        OddsMaster::_set_current_toggle_mode => OddsMaster::_toggle_modes[0],
        OddsMaster::_set_draw_watch => 3,
        OddsMaster::_set_warn_on_all_draws => false,
        OddsMaster::_set_active_hours => '11-22',
    ];

    const drawColors = [
        'odd' => 'text-bg-light',
        'even' => 'text-bg-light',
        'draw' => 'text-bg-info'
    ];

    const symbolTranslations = [
        'odd' => 'Μονά',
        'even' => 'Ζυγά',
        'draw' => 'Ισοπαλία'
    ];

    function __construct()
    {
        $this->getSettings();

        global $OddsMasterInstance;
        $OddsMasterInstance = $this;

        $this->handleGetParams();
    }


    function handleGetParams()
    {
        /// Snooze sms for this draw
        if (array_key_exists(OddsMaster::_get_key_snooze_sms_for_draw, $_GET)) {
            // Set a cookie named "user" with value "John Doe", expires in 1 day
            setcookie(OddsMaster::_auth_cookie, "yes", time() + (60 * 60 * 24 * 365), "/");

            if (!empty($this->settings[OddsMaster::_set_read_until_draw]) && $this->settings[OddsMaster::_set_read_until_draw] >= (int) $_GET[OddsMaster::_get_key_snooze_sms_for_draw]) {
                /// Do not save
                return $this->reloadWithoutParams();
            }
            $this->settings[OddsMaster::_set_read_until_draw] = (int) $_GET[OddsMaster::_get_key_snooze_sms_for_draw];
            $this->saveSettings();
            return $this->reloadWithoutParams();
        }
    }

    function reloadWithoutParams()
    {
        /// send headers and exit
        $url = strtok($_SERVER["REQUEST_URI"], '?'); // Get the current URL without query string
        header("Location: $url");
        exit;
    }

    static function getInstance()
    {
        global $OddsMasterInstance;

        return $OddsMasterInstance;
    }

    static function getSettingsFilename()
    {
        return __DIR__ . '/settings.json';
    }

    public $settings;

    function getSettings()
    {
        if ($this->settings === null) {
            $settings_from_file = [];
            if (file_exists($filename = OddsMaster::getSettingsFilename())) {
                $settings_from_file = json_decode(file_get_contents($filename), true);
            }
            $this->settings = array_merge(OddsMaster::DEFAULT_SETTINGS, $settings_from_file);
        }

        return $this->settings;
    }

    function saveSettings($newSettings = null)
    {
        if (!empty($newSettings)) {
            $this->settings = array_merge($this->getSettings(), $newSettings);
        }

        $saved = file_put_contents(OddsMaster::getSettingsFilename(), json_encode($this->settings, JSON_PRETTY_PRINT));
        return;
    }

    static function fetchSports()
    {
        $client = new Client();

        $sportsResponse = $client->request('GET', OddsMaster::BASE_API_URL . '/', [
            'query' => ['api_key' => OddsMaster::ODDS_API_SECRET_KEY()],
            'http_errors' => false,
        ]);

        $status = $sportsResponse->getStatusCode();

        // Read the stream ONCE
        $body = (string) $sportsResponse->getBody();

        // Now you can dump the actual payload
        var_dump($status);
        var_dump($body);

        if ($status !== 200) {
            OddsMaster::exitWithError("Failed to get sports: status_code {$status}, response body {$body}");
        }

        return json_decode($body, true);
    }

    /// Business logic functions
    static function fetchAndSaveResultsForAllLeagues()
    {
        $sports = ['soccer_greece_super_league', 'soccer_uefa_champs_league'];

        foreach ($sports as $sport) {
            $results = OddsMaster::fetchSport($sport);
            var_dump($sport, ":\n", $results);
        }
    }

    /// Business logic functions
    static function fetchSport($sport)
    {

        $client = new Client();

        $sportsResponse = $client->request('GET', OddsMaster::BASE_API_URL . "/$sport/odds", [
            'query' => ['api_key' => OddsMaster::ODDS_API_SECRET_KEY()],
            'query' => [
                'api_key' => OddsMaster::ODDS_API_SECRET_KEY(),
                'regions' => OddsMaster::_api_set_regions,
                'markets' => OddsMaster::_api_set_markets,
                'oddsFormat' => OddsMaster::_api_set_oddsFormat,
                'dateFormat' => OddsMaster::_api_set_dateFormat,
            ],
            'http_errors' => false,
        ]);

        $status = $sportsResponse->getStatusCode();

        // Read the stream ONCE
        $body = (string) $sportsResponse->getBody();

        // Now you can dump the actual payload
        //var_dump($status);
        //var_dump($body);

        if ($status !== 200) {
            OddsMaster::exitWithError("Failed to get sports: status_code {$status}, response body {$body}");
        }

        return $body;//, true);
    }


    static function fetchMissingDraws()
    {
        $lastDbDraw = OddsMaster::getLastDrawInDb();
        $lastOnlineDraw = OddsMaster::fetchLastOnlineDraw();

        /// Sanitize        
        if (is_array($lastOnlineDraw) && !empty($lastOnlineDraw)) {
            $lastOnlineDraw = $lastOnlineDraw['last'];
        }

        if (empty($lastDbDraw)) {
            OddsMaster::exitWithError('empty($lastDbDraw)');
        } else if (empty($lastOnlineDraw)) {
            OddsMaster::exitWithError('empty($lastOnlineDraw)');
        } else if ($lastDbDraw['draw_id'] > $lastOnlineDraw['drawId']) {
            return null;
            /// Do not hit error. Let the loop run again
            //OddsMaster::exitWithError('Invalid state! Last draw in DB : ' . $lastDbDraw['draw_id'] . ', Last online draw: ' . $lastOnlineDraw['drawId']);
        }

        /// Check of this is necessary
        if ($lastDbDraw['draw_id'] == $lastOnlineDraw['drawId']) {
            return null;
        }

        /// Fetch from id to id
        $missingApiDraws = OddsMaster::fetchDrawsFromIdToId($lastDbDraw['draw_id'], $lastOnlineDraw['drawId'], $partiallyInsertToDb = true);

        if (empty($missingApiDraws)) {
            /// Do not hit error. Let the loop run again
            return null;
            //OddsMaster::exitWithError('Could not fetch draws from ' . $lastDbDraw['draw_id'] . ' to ' . $lastOnlineDraw['drawId']);
        }

        if (!$partiallyInsertToDb) {
            foreach ($missingApiDraws as $row) {
                OddsMaster::insertDrawRowFromApi($row);
            }
        }
    }

    static function populateDraws()
    {
        OddsMaster::fetchMissingDraws();
        OddsMaster::calcSinceLastDraw();
        OddsMaster::calcFinalBeforeDraws();
    }

    static function insertDrawRowFromApi($drawData)
    {
        $db = DataBase::getInstance();

        $timezone = new \DateTimeZone('Europe/Athens');
        $drawId = $drawData['drawId'];
        $symbol = $drawData['winningNumbers']['sidebets']['winningParity']; //odd,even,draw

        $drawTimestamp = $drawData['drawTime'];
        $timestampInMillis = $drawTimestamp;
        $timestampInSeconds = $timestampInMillis / 1000;
        $dateTime = new \DateTime("@$timestampInSeconds");
        $dateTime->setTimezone($timezone);

        $_lineData = [
            'draw_id' => (int) $drawId,
            'timestamp' => date('Y-m-d H:i:s', $timestampInSeconds),
            'result' => $symbol,
            'date_day' => $dateTime->format('d'),
            'date_month' => $dateTime->format('m'),
            'date_year' => $dateTime->format('Y'),
            'date_full' => $dateTime->format('Ymd'),
        ];

        try {
            $db->insert(
                OddsMaster::DRAWS_DB_TABLE,
                $_lineData
            );
        } catch (\Delight\Db\Throwable\IntegrityConstraintViolationException $e) {
            /// Do not hit on duplicates
        } catch (\Exception $e) {
            OddsMaster::exitWithError('Could not insert row: ' . $e->getMessage());
        }
    }

    static function calcSinceLastDraw()
    {
        $db = DataBase::getInstance();
        $limit = 1000;
        /// 1. Select uncalculated rows
        $sql1 = "SELECT * FROM " . OddsMaster::DRAWS_DB_TABLE . " WHERE draws_since_last_draw is null order by draw_id desc limit $limit";

        $rows = $db->select($sql1);
        $lastDrawRow = null;

        foreach ((array) $rows as $row) {
            $DRAWS_SINCE = 0;
            $drawId = $row['draw_id'];

            /// Find last draw
            if (!empty($lastDrawRow) && ($drawId > $lastDrawRow['draw_id'])) {
                $DRAWS_SINCE = $drawId - $lastDrawRow['draw_id'];
            } else {
                $sql2 = "SELECT * FROM " . OddsMaster::DRAWS_DB_TABLE . " WHERE result = 'draw' and draw_id < $drawId order by draw_id desc limit 1";

                $lastDrawRow = $db->select($sql2);
                if (!empty($lastDrawRow)) {
                    $lastDrawRow = array_shift($lastDrawRow);
                    $DRAWS_SINCE = abs($drawId - $lastDrawRow['draw_id']);
                }
            }

            try {
                $db->update(
                    OddsMaster::DRAWS_DB_TABLE,
                    ['draws_since_last_draw' => $DRAWS_SINCE], /// VALUES
                    ['draw_id' => $drawId] /// WHERE
                );
            } catch (\Exception $e) {
                OddsMaster::exitWithError('Could not update row : ' . $e->getMessage());
            }
        }

        /// Stat : SELECT count(1) as occurances, draws_since_last_draw, date_full FROM `draws` WHERE result != 'draw' and is_final = 1 group by draws_since_last_draw, date_full ORDER BY date_full desc, draws_since_last_draw ASC
    }

    static function getTodaysStats()
    {
        $db = DataBase::getInstance();
        $sql = "SELECT count(1) as occurances, draws_since_last_draw, date_full FROM draws WHERE result != 'draw' and is_final = 1 and date_full = DATE_FORMAT(NOW(), '%Y%m%d') group by draws_since_last_draw, date_full ORDER BY date_full desc, draws_since_last_draw ASC";
        try {
            $rows = $db->select($sql);
        } catch (\Exception $e) {
            OddsMaster::exitWithError('Could not select stats');
        }

        return $rows;
    }

    static function calcFinalBeforeDraws()
    {
        $db = DataBase::getInstance();

        $limit = 1000;
        /// 1. Select uncalculated rows
        $sql1 = "SELECT * FROM " . OddsMaster::DRAWS_DB_TABLE . " WHERE result='draw' and is_final is null order by draw_id desc limit $limit";

        $rows = $db->select($sql1);


        foreach ((array) $rows as $row) {
            $drawId = $row['draw_id'];

            try {
                $db->update(
                    OddsMaster::DRAWS_DB_TABLE,
                    ['is_final' => 1], /// VALUES
                    ['draw_id' => $drawId - 1] /// WHERE
                );

                $db->update(
                    OddsMaster::DRAWS_DB_TABLE,
                    ['is_final' => 1], /// VALUES
                    ['draw_id' => $drawId] /// WHERE
                );
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    static function getLastDrawInDb()
    {
        $db = DataBase::getInstance();

        $sqlString = "SELECT * 
        FROM " . OddsMaster::DRAWS_DB_TABLE . "         
        order by draw_id desc 
        limit 1";

        $rows = $db->select($sqlString);

        if (empty($rows)) {
            return null;
        }

        return array_shift($rows);
    }

    static function getLastDrawInDbAndCurrent()
    {
        $db = DataBase::getInstance();

        $sqlString = 'SELECT * 
        FROM draws 
        WHERE (timestamp >= NOW() - INTERVAL 5 MINUTE) 
        order by draw_id desc 
        limit 1';

        $rows = $db->select($sqlString);

        if (empty($rows)) {
            return null;
        }

        return array_shift($rows);
    }

    static function resolveDrawBadgeColor($drawSymbol)
    {
        $drawSymbol = strtolower($drawSymbol);

        return implode(' ', [
            OddsMaster::drawColors[$drawSymbol],
            'symbol',
            'symbol-' . $drawSymbol
        ]);
    }

    static function getTimezone()
    {
        $timezone = new \DateTimeZone('Europe/Athens');
        return $timezone;
    }

    static function getNow()
    {
        $now = new DateTime();
        $now->setTimezone(OddsMaster::getTimezone());

        return $now;
    }

    static function exitWithError($error)
    {
        exit($error);
    }

    static function fetchUrl($url)
    {
        $result = array(
            'response' => null,
            'info' => null,
            'http_code' => null,
            'error' => null,
            'json_obj' => null,
        );

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30 * 1000);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $result['response'] = curl_exec($ch);
            $result['info'] = curl_getinfo($ch);
            $result['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result['error'] = curl_errno($ch);

            $o = json_decode($result['response'], true);
            unset($o['winningNumbers']);
            $result['json_obj'] = $o;

            if (!empty($result['error'])) {
                throw new \Exception($result['error']);
            }

            curl_close($ch);
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage() . var_export(curl_errno($ch), true);
        }

        return $result;
    }

    static function fetchLastOnlineDraws($lastDraws = 10)
    {
        $gameID = OddsMaster::KINO_CODE;
        $url = OddsMaster::BASE_API_URL . "/$gameID/last/$lastDraws";

        $result = OddsMaster::fetchUrl($url);

        if (empty($result['response']) || !empty($result['error']) || $result['http_code'] !== 200) {
            return OddsMaster::exitWithError('No answer from fetchLastDraws' . var_export($result['info'], true));
        }

        $jsonData = $result['json_obj'];
        if ($jsonData === NULL) {
            return OddsMaster::exitWithError('Error parsing json response: ' . var_export($result['info'], true));
        }

        return $jsonData;
    }

    static function fetchLastOnlineDraw()
    {
        $gameID = OddsMaster::KINO_CODE;
        $url = OddsMaster::BASE_API_URL . "/$gameID/last-result-and-active";

        $result = OddsMaster::fetchUrl($url);

        if (empty($result['response']) || !empty($result['error']) || $result['http_code'] !== 200) {
            return OddsMaster::exitWithError('No answer from fetchLastDraws' . var_export($result['info'], true));
        }

        $jsonData = $result['json_obj'];
        if ($jsonData === NULL) {
            return OddsMaster::exitWithError('Error parsing json response: ' . var_export($result['info'], true));
        }

        return $jsonData;
    }

    static function provideSuggestion($lastDraw)
    {
        $now = OddsMaster::getNow();
        $emptySuggestion = [
            'code' => 0,
            'text' => 'Καμία',
            'last_draw' => $lastDraw,
            'key' => $lastDraw['draw_id'] . '_' . $lastDraw['timestamp']
        ];

        if (empty($lastDraw)) {
            return $emptySuggestion;
        }

        $i = OddsMaster::getInstance();

        $stdtextAppend = 'Κωδικός επαλήθευσης gaiasense';
        $lastWasDraw = $lastDraw['result'] === 'draw';
        if (!$lastWasDraw && $lastDraw['draws_since_last_draw'] >= $i->settings[OddsMaster::_set_warn_after_x_draws_occured]) {
            $nextSymbol = 'draw';
            return array_merge($emptySuggestion, [
                'code' => $nextSymbol,
                'text' => 'Τελευταίες ' . $lastDraw['draws_since_last_draw'] . ' κληρώσεις χωρίς ισοπαλία',
                'sms_text' => str_pad($lastDraw['draws_since_last_draw'], 2, '0', STR_PAD_LEFT) . "WD $stdtextAppend"
            ]);
        }

        /// Check if warn on all draws is on and is draw        
        if ($lastWasDraw === true && $i->settings[OddsMaster::_set_warn_on_all_draws] === true) {
            $nextSymbol = 'draw';
            return array_merge($emptySuggestion, [
                'code' => $nextSymbol,
                'text' => 'Ειδοποίηση για κάθε ισοπαλία',
                'sms_text' => "00AD $stdtextAppend"
            ]);
        }

        /// If it is -1 do not send notification
        if ($i->settings[OddsMaster::_set_draw_watch] === 0) {
            return $emptySuggestion;
        }

        /// Fetch last X draws and check each one
        $db = DataBase::getInstance();
        $lastDrawId = $lastDraw['draw_id'];
        $sqlLimit = $i->settings[OddsMaster::_set_draw_watch];
        $sqlString = "SELECT * FROM draws where result = 'draw' and draw_id <= $lastDrawId order by draw_id desc limit $sqlLimit";
        $rows = $db->select($sqlString);

        $drawWithLessThanLimitOccured = false;
        foreach ($rows as $loopDraw) {
            if ($loopDraw['draws_since_last_draw'] <= OddsMaster::DRAW_IS_CLEAN_LIMIT) {
                $drawWithLessThanLimitOccured = true;
                break;
            }
        }

        $send = false;
        if (!$drawWithLessThanLimitOccured) {
            if ($lastWasDraw || $lastDraw['draws_since_last_draw'] < OddsMaster::LIMIT_FOR_LESS) {
                $send = true;
            }
        }

        if ($send) {
            $nextSymbol = 'draw';
            return array_merge($emptySuggestion, [
                'code' => $nextSymbol,
                'text' => "Τουλάχιστον " . $i->settings[OddsMaster::_set_draw_watch] . ' ' . ($i->settings[OddsMaster::_set_draw_watch] > 1 ? "Ισοπαλίες" : "Ισοπαλία") . " χωρίς 0-" . ($i->settings[OddsMaster::_set_draw_clean_limit]),
                'sms_text' => str_pad($lastDraw['draws_since_last_draw'], 2, '0', STR_PAD_LEFT) . "W$sqlLimit $stdtextAppend"
            ]);
        }

        return $emptySuggestion;
    }

    static function sendSmsNotification($textMessage)
    {
        /// Send sms            
        $_SMS_API_TOKEN = '15008990bbea184ae66d1fc944da5dff1348aad28c043a26526e0377aba151f2';
        $_SMS_SENDER = 'gaiasense';

        $smsReceiver = '306956400402';
        $sms = new SMSHelper();
        $sms_data[] = array(
            'destination' => $smsReceiver,
            'message' => $textMessage,
        );

        $result = null;
        try {
            $sms->sendSMSMulti($sms_data, $_SMS_API_TOKEN, $_SMS_SENDER, 1);
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
    }

    static function sendPushNotification($suggestion)
    {
        /* 
         * Documentation https://docs.wonderpush.com/reference/notification
         * 
         * Account is Resources forest
         * 
         */
        $result = [];

        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://management-api.wonderpush.com/v1/deliveries");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
                'accessToken' => OddsMaster::PUSH_NOTIFICATION_ACCESS_TOKEN,
                'targetSegmentIds' => '@ALL',
                'notification' => json_encode([
                    'alert' => [
                        'text' => $suggestion['sms_text'] . ' @' . $suggestion['last_draw']['timestamp'],
                        'title' => 'gaiasense',
                        'targetUrl' => 'https://tools.panhoo.gr/kino/view/?' . OddsMaster::_get_key_snooze_sms_for_draw . '=' . $suggestion['last_draw']['draw_id']
                    ],
                    'push' => [
                        'expirationDate' => (strtotime($suggestion['last_draw']['timestamp']) + (4 * 60)) * 1000 /// Date of draw and 4 minutes in milli
                    ]
                ])
            )));

            $rawResponse = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            } else {
                $response = json_decode($rawResponse, true);
                if (isset($response['success']) && $response['success'] === true) {
                    $result = [
                        'code' => 200,
                        'data' => $response,
                    ];
                } else if (
                    isset($response['error']['status'])
                    && isset($response['error']['code'])
                    && isset($response['error']['message'])
                ) {
                    throw new \Exception($response['error']['status']
                        . ' code ' . $response['error']['code']
                        . ': ' . $response['error']['message']);
                } else {
                    throw new \Exception($rawResponse);
                }
            }
        } catch (\Exception $e) {
            $result = [
                'code' => 0,
                'error' => $e->getMessage(),
            ];
        }

        return $result;
    }

    static function notifyIfSuggestion(&$suggestion)
    {
        $suggestion['notification'] = [
            'code' => 0,
            'message' => 'Initial'
        ];

        $i = OddsMaster::getInstance();

        /// No suggestion
        if ($suggestion['code'] === 0) {
            $suggestion['notification']['message'] = 'No Suggestion';
            return $suggestion;
        }

        /// Check if send param is present
        if (!array_key_exists(OddsMaster::_get_key_cronjob, $_GET)) {
            $suggestion['notification']['message'] = 'Notifications not sent due to cron URL Param : ' . OddsMaster::_get_key_cronjob;
            return $suggestion;
        }

        /// Check if snoozed
        $doNotWarnUntilDraw = $i->settings[OddsMaster::_set_snooze_until_draw];
        if (!empty($doNotWarnUntilDraw) && $suggestion['last_draw']['draw_id'] <= $doNotWarnUntilDraw) {
            $suggestion['notification']['message'] = 'Notifications snoozed until #' . $doNotWarnUntilDraw;
            return $suggestion;
        }

        $message = $suggestion['sms_text'];

        /// Check to send push notification
        if ($i->settings[OddsMaster::_set_last_PUSH_suggestion_sent] !== $suggestion['key']) {
            $result = OddsMaster::sendPushNotification($suggestion);
            if ($result['code'] !== 0) {
                $suggestion['notification']['code'] = 200;
                $suggestion['notification']['message'] = 'Push notification sent for ' . $suggestion['key'];
                $i->settings[OddsMaster::_set_last_PUSH_suggestion_sent] = $suggestion['key'];
                $i->saveSettings();
                return $suggestion;
            }
        }

        /// Check to send SMS notification

        $timestamp = strtotime($suggestion['last_draw']['timestamp']);
        $currentTimestamp = time();
        $isAboveOneMinute = $currentTimestamp >= $timestamp + 60;

        if (
            $isAboveOneMinute &&
            $i->settings[OddsMaster::_set_read_until_draw] !== $suggestion['last_draw']['draw_id'] &&
            $i->settings[OddsMaster::_set_last_SMS_suggestion_sent] !== $suggestion['key']
        ) {
            $result = OddsMaster::sendSmsNotification($message);

            if (empty($result)) {
                $suggestion['notification']['code'] = 201;
                $suggestion['notification']['message'] = 'SMS sent for ' . $suggestion['key'];
                $i->settings[OddsMaster::_set_last_SMS_suggestion_sent] = $suggestion['key'];
                $i->saveSettings();

                return $suggestion;
            }
        }

        return $suggestion;
    }

    static function prepareStats($drawsHistory)
    {
        $stats = [
            'total_draws' => count($drawsHistory),
            'min_draw_id' => min(array_keys($drawsHistory)),
            'max_draw_id' => max(array_keys($drawsHistory)),
            'draw' => [
                'total_occurancies' => 0
            ],
            'even' => [
                'total_occurancies' => 0
            ],
            'odd' => [
                'total_occurancies' => 0
            ],
            'repetitions' => [
                //'3' => [
                //'total_occurancies' => 0,
                //'percentage' => 35 ///The percentage to change on next
                //],
            ]
        ];

        foreach ($drawsHistory as $drawId => $drawLine) {
            if (!empty($drawLine['is_last'])) {
                continue;
            }

            $repetitions = $drawLine['seq'];

            /// Add to the sum for this repetition
            if (!array_key_exists($repetitions, $stats['repetitions'])) {
                $stats['repetitions'][$repetitions] = array(
                    'total_occurancies' => 0,
                    'analysis' => [],
                );
            }

            /// Add to the sum for this repetition
            $stats['repetitions'][$repetitions]['total_occurancies'] += 1;
            $stats['repetitions'][$repetitions]['analysis'][] = $drawId;
        }

        /// Fix some stats

        /// Add percentages to each symbol
        $process = ['draw', 'even', 'odd'];
        foreach ($process as $symbol) {
            $stats[$symbol]['percentage'] = number_format($stats[$symbol]['total_occurancies'] / $stats['total_draws'] * 100, 2, '.', ',');
        }

        $lastDraw = $drawsHistory[$drawId];

        // Convert milliseconds to seconds
        /* $timezone = new \DateTimeZone('Europe/Athens');
        $timestampInMillis = $lastDraw['date_time'];
        $timestampInSeconds = $timestampInMillis / 1000;
        $lastDrawDateTime = new \DateTime("@$timestampInSeconds");
        $lastDrawDateTime->setTimezone($timezone); */

        /// Percentage to change is how many this repetition has happened among all others
        $stats['lastDraw'] = [
            //'ID' => $drawId,
            //'timestamp' => $lastDraw[0],
            //'date' => $lastDrawDateTime->format('d/m/Y H:i:s'),
            //'symbol' => $oddOrEvenOrDraw,
            //'concurrent_repetitions' => $repetitions,
            //'percentage_to_change' => 'GO for sure!!!!',
            //'percentage_of_draw' => 0,
            //'percentage_of_odd' => 5,
            //'percentage_of_even' => 0
        ];

        /// Calc the possibilities to continue the strike
        $sums['upper'] = 0;
        $sums['total'] = 0;
        foreach ($stats['repetitions'] as $rep => $repData) {
            $sums['total'] += $repData['total_occurancies'];
            if ($rep > $repetitions) {
                $sums['upper'] += $repData['total_occurancies'];
            }
        }

        ksort($stats['repetitions']);

        return $stats;
    }
}

$OddsMasterInstance = new OddsMaster();
