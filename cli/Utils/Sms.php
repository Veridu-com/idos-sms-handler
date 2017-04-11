<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */
declare(strict_types = 1);

namespace Cli\Utils;

/**
 * Command definition for Process-based Daemon.
 */
class Sms {
    private $endpoint;
    private $username;
    private $password;

    public function __construct(array $settings) {
        $this->endpoint = $settings['endpoint'];
        $this->username = $settings['username'];
        $this->password = $settings['password'];
    }

    /**
     * Return the error message given the error code.
     *
     * @param mixed $code
     *
     * @return string
     */
    private function checkApiError($code) {
        switch ($code) {
            case -1:
                return 'No error';
            case 8:
                return 'Error in request (Please report)';
            case 11:
                return 'Message too long or there is no message or parameter nounicode is set and special characters (including Polish characters) are used.';
            case 12:
                return 'Message has over 160 chars when parameter &single=1';
            case 13:
                return 'Invalid phone number';
            case 14:
                return 'Wrong sender name';
            case 17:
                return 'FLASH message cannot contain special characters';
            case 18:
                return 'Invalid number of parameters';
            case 19:
                return 'Too many messages in one request';
            case 20:
                return 'Invalid number of IDX parameters';
            case 30:
                return 'Wrong UDH parameter when &datacoding=bin';
            case 40:
                return 'No group with given name in phonebook';
            case 41:
                return 'Chosen group is empty';
            case 50:
                return 'Messages may be scheduled up to 3 months in the future';
            case 52:
                return 'Too many attempts of sending messages to one number (maximum 10 attempts whin 60s)';
            case 53:
                return 'Not unique idx parameter, message with the same idx has been already sent and &check_idx=1.';
            case 54:
                return 'Wrong date - (only unix timestamp and ISO 8601)';
            case 56:
                return 'The difference between date sent and expiration date can\'t be less than 1 and more tha 12 hours.';
            case 101:
                return 'Invalid authorization info';
            case 102:
                return 'Invalid username or password';
            case 103:
                return 'Insufficient credits on Your account';
            case 104:
                return 'No such template';
            case 105:
                return 'Wrong IP address (for IP filter turned on)';
            case 200:
                return 'Unsuccessful message submission';
            case 201:
                return 'System internal error (please report)';
            case 202:
                return 'Too many simultaneous request, message won\'t be sent';
            case 301:
                return 'ID of messages doesn\'t exist';
            case 400:
                return 'Invalid message ID of a status response';
            case 999:
                return 'System internal error (please report)';
            case 1000:
                return 'Acction available only for main user';
            case 1001:
                return 'Invalid action (expected one of following parameters: add_user, set_user, get_user, credits)';
            case 1010:
                return 'Subuser\'s adding error';
            case 1020:
                return 'Subuser\'s editing error';
            case 1021:
                return 'No data to edit, at least one parameter has to be edited';
            case 1030:
                return 'Checking user\'s data error';
            case 1032:
                return 'Subuser doesn\'t exist for this main user';
            case 1100:
                return 'Subuser\'s data error';
            case 1110:
                return 'Invalid new subuser\'s name';
            case 1111:
                return 'New subuser\'s name is missing';
            case 1112:
                return 'Too short new subuser\'s name, it has to contain minimum 3 characters';
            case 1113:
                return 'Too long new subuser\'s name, subuser\'s name with main user\'s prefix may contain maximum 32 characters';
            case 1114:
                return 'Not allowed characters occured in subuser\'s name, following are allowed: letters [A - Z], digits [0 - 9] and following others @, -, _ and .';
            case 1115:
                return 'Another user with the same name exists';
            case 1120:
                return 'New subuser\'s password error';
            case 1121:
                return 'Password too short';
            case 1122:
                return 'Password too long';
            case 1123:
                return 'Password should be hashed with MD5';
            case 1130:
                return 'Credit limit error';
            case 1131:
                return 'Parameter limit ought to be a number';
            case 1140:
                return 'Month limit error';
            case 1141:
                return 'Parameter month_limit ought to be a number';
            case 1150:
                return 'Wrong senders parameter vaule, binnary 0 and 1 values allowed';
            case 1160:
                return 'Wrong phonebook parameter vaule, binnary 0 and 1 values allowed';
            case 1170:
                return 'Wrong active parameter vaule, binnary 0 and 1 values allowed';
            case 1180:
                return 'Parameter info error.';
            case 1183:
                return 'Parameter info is too long.';
            case 1190:
                return 'API password for subuser\'s account error.';
            case 1192:
                return 'Wrong API password lenght (password hashed with MD5 should have 32 chars)';
            case 1193:
                return 'API password should be hashed with MD5';
            case 2001:
                return 'Invalid action (parameter add, status, delete or list expected)';
            case 2010:
                return 'New sender name adding error';
            case 2030:
                return 'Sender name\'s status checking error';
            case 2031:
                return 'Such sender name doesn\'t exist';
            case 2060:
                return 'Default sender name error';
            case 2061:
                return 'Sender name has to be active for setting it as default';
            case 2062:
                return 'This sender name is already set as default';
            case 2100:
                return 'Data error';
            case 2110:
                return 'Sender name error';
            case 2111:
                return 'Sender name is missing for adding ne sender name actionBrak nazwy dodawanego pola nadawcy (parameter &add is empty)';
            case 2112:
                return 'Invalid Sender Name\'s name (i.e. Name containing special chars or name too long), sender name may contain up to 11 chars, chars allowed: a-z A-Z 0-9 - . [spacebar]';
            case 2115:
                return 'Sender name already exist';
            default:
                return "Unknown code {$code}";
        }
    }

    /**
     * Check if the response has a valid format and has no error.
     *
     * @param mixed $response
     *
     * @return bool
     */
    private function checkResponse($response) {
        if (strncmp($response, 'OK:', 3) == 0) {
            return true;
        }

        if (strncmp($response, 'ERROR:', 6) == 0) {
            $this->lastError = substr($response, 6);
        } else {
            $this->lastError = $response;
        }

        return false;
    }

    /**
     * Return the last error message.
     *
     * @return string
     */
    public function lastError() {
        return $this->checkApiError($this->lastError);
    }

    public function fix($number, $msg) {
        if (strlen($number) == 14)
            $number = '+44' . substr($number, -10);
        else
            $number = '+440' . substr($number, -10);
        try {
            $response = \Requests::post(
                $this->endpoint,
                [],
                [
                    'username' => $this->username,
                    'password' => md5($this->password),
                    'to'       => $number,
                    'from'     => 'Veridu',
                    'fast'     => 1,
                    'message'  => $msg
                ]
            );
            if ($response->status_code != 200) {
                $this->log->alert("Failed to send sms to {$number}: HTTP Status {$response->status_code}");
                $this->lastError = 'Failed to contact SMS Gateway';

                return false;
            }

            if ($this->checkResponse($response->body))
                return true;
            $this->log->alert("Failed to send sms to {$number}: " . $this->lastError());
        } catch (\Exception $e) {
            $this->log->alert("Failed to send sms to {$number}: " . $e->getMessage());
            $this->lastError = 'Failed to contact SMS Gateway';
        }

        return false;
    }

    /**
     * Send the SMS message.
     *
     * @param mixed $number
     * @param mixed $msg
     *
     * @return bool
     */
    public function send($number, $msg) : bool {
        $this->lastError = -1;
        //ugly hack/fix to overcome with a gateway problem with deliveries
        if (strncmp($number, '+44', 3) == 0)
            $this->fix($number, $msg);
        try {
            $client   = new \GuzzleHttp\Client();
            $response = $client->request(
                'POST',
                $this->endpoint,
                [
                    'query' => [
                        'username' => $this->username,
                        'password' => md5($this->password),
                        'to'       => $number,
                        'from'     => 'Veridu',
                        'fast'     => 1,
                        'message'  => $msg
                    ]
                ]
            );

            if ($response->getStatusCode() != 200) {
                $this->log->alert('Failed to send sms to ' . $number . ': HTTP Status ' . $response->getStatusCode());
                $this->lastError = 'Failed to contact SMS Gateway';

                return false;
            }

            if ($this->checkResponse((string) $response->getBody())) {
                return true;
            }

            $this->log->alert("Failed to send sms to {$number}: " . $this->lastError());
        } catch (\Exception $e) {
            $this->log->alert("Failed to send sms to {$number}: " . $e->getMessage());
            $this->lastError = 'Failed to contact SMS Gateway';
        }

        return false;
    }
}
