Operation manual
=================

# Configuration

You need to set some environment variables in order to configure the SMS daemon, such as in the following example:

* `IDOS_VERSION`: indicates the version of idOS API to use (default: '1.0');
* `IDOS_DEBUG`: indicates whether to enable debugging (default: false);
* `IDOS_LOG_FILE`: is the path for the generated log file (default: 'log/cra.log');
* `IDOS_GEARMAN_SERVERS`: a list of gearman servers that the daemon will register on (default: 'localhost:4730');
* `IDOS_SMS_ENDPOINT`: the URL for the SMS API endpoint (default: 'https://api.smsapi.com/sms.do');
* `IDOS_SMS_USER`: the username to authenticate within the SMS API;
* `IDOS_SMS_PASS`: the password to authenticate within the SMS API.

You may also set these variables using a `.env` file in the project root.

# Running

In order to start the SMS daemon you should run in the terminal:

```
./sms-cli.php sms:daemon [-d] [-l path/to/log/file] functionName serverList
```

* `functionName`: gearman function name
* `serverList`: a list of the gearman servers
* `-d`: enable debug mode
* `-l`: the path for the log file

Example:

```
./sms-cli.php sms:daemon -d -l log/sms.log sms localhost
```