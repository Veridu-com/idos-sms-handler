<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace Cli\Command;

use Cli\Utils;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\UidProcessor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command definition for the SMS Daemon.
 */
class Daemon extends AbstractCommand {
    /**
     * Command configuration.
     *
     * @return void
     */
    protected function configure() {
        $this
            ->setName('sms:daemon')
            ->setDescription('idOS SMS - Daemon')
            ->addOption(
                'devMode',
                'd',
                InputOption::VALUE_NONE,
                'Development mode'
            )
            ->addOption(
                'healthCheck',
                'c',
                InputOption::VALUE_NONE,
                'Enable queue health check'
            )
            ->addOption(
                'logFile',
                'l',
                InputOption::VALUE_REQUIRED,
                'Path to log file'
            )
            ->addArgument(
                'functionName',
                InputArgument::REQUIRED,
                'Gearman Worker Function name'
            )
            ->addArgument(
                'serverList',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Gearman server host list (separate values by space)'
            );
    }

    /**
     * Command execution. This method will start the daemon.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $outpput
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $logFile = $input->getOption('logFile') ?? 'php://stdout';
        $logger  = new Monolog('SMS');
        $logger
            ->pushProcessor(new ProcessIdProcessor())
            ->pushProcessor(new UidProcessor())
            ->pushHandler(new StreamHandler($logFile, Monolog::DEBUG));

        $logger->debug('Initializing idOS SMS Handler Daemon');

        $bootTime = time();

        // Development mode
        $devMode = ! empty($input->getOption('devMode'));
        if ($devMode) {
            $logger->debug('Running in developer mode');
            ini_set('display_errors', 'On');
            error_reporting(-1);
        }

        // Health check
        $healthCheck = ! empty($input->getOption('healthCheck'));
        if ($healthCheck) {
            $logger->debug('Enabling health check');
        }

        // Gearman Worker function name setup
        $functionName = $input->getArgument('functionName');
        if ((empty($functionName)) || (! preg_match('/^[a-zA-Z0-9\._-]+$/', $functionName))) {
            $functionName = 'sms';
        }

        // Server List setup
        $servers = $input->getArgument('serverList');

        $gearman = new \GearmanWorker();
        foreach ($servers as $server) {
            if (strpos($server, ':') === false) {
                $logger->debug(sprintf('Adding Gearman Server: %s', $server));
                @$gearman->addServer($server);
                continue;
            }

            $server    = explode(':', $server);
            $server[1] = intval($server[1]);
            $logger->debug(sprintf('Adding Gearman Server: %s:%d', $server[0], $server[1]));
            @$gearman->addServer($server[0], $server[1]);
        }

        // Run the worker in non-blocking mode
        $gearman->addOptions(\GEARMAN_WORKER_NON_BLOCKING);

        // 1 second I/O timeout
        $gearman->setTimeout(1000);

        $logger->debug('Registering Worker Function', ['function' => $functionName]);

        $jobCount = 0;
        $lastJob  = 0;
        $sms      = new Utils\Sms($this->config['sms']);

        /*
         * Payload content:
         * FIXME: Add payload
         */
        $gearman->addFunction(
            $functionName,
            function (\GearmanJob $job) use ($sms, $logger, $devMode, &$jobCount, &$lastJob) {
                $logger->info('SMS job added');
                $jobData = json_decode($job->workload(), true);
                if ($jobData === null || ! isset($jobData['template'])) {
                    $logger->warning('Invalid Job Workload!');
                    $job->sendComplete('invalid');

                    return;
                }

                $jobCount++;
                $init = microtime(true);

                switch($jobData['template']) {
                    case 'otp':
                        $message = 'Your verification code is ' . $jobData['variables']['password'];
                        break;

                    default:
                        $logger->warning('Invalid Job Workload!');
                        $job->sendComplete('invalid');

                        return;
                }

                $sms->send($jobData['phone'], $message);

                $logger->info('Job completed', ['time' => microtime(true) - $init]);
                $job->sendComplete('ok');
                $lastJob = time();
            }
        );

        $logger->debug('Entering Gearman Worker Loop');

        $serverFailure = 0;

        // Gearman's Loop
        while (@$gearman->work()
                || ($gearman->returnCode() == \GEARMAN_IO_WAIT)
                || ($gearman->returnCode() == \GEARMAN_NO_JOBS)
                || ($gearman->returnCode() == \GEARMAN_TIMEOUT)
        ) {
            if ($gearman->returnCode() == \GEARMAN_SUCCESS) {
                $serverFailure = 0;
                continue;
            }

            if (! @$gearman->wait()) {
                if ($gearman->returnCode() == \GEARMAN_NO_ACTIVE_FDS) {
                    // No server connection, sleep before reconnect
                    $serverFailure++;
                    if ($serverFailure > 3) {
                        $logger->warning('Invalid server state, restarting');
                        exit;
                    }

                    $logger->debug('No active server, sleep before retry');
                    sleep(5);
                    continue;
                }

                if ($gearman->returnCode() == \GEARMAN_TIMEOUT) {
                    // Job wait timeout, sleep before retry
                    sleep(1);
                    if (! @$gearman->echo('ping')) {
                        $logger->debug('Invalid server state, restarting');
                        exit;
                    }

                    if (($healthCheck) && ((time() - $bootTime) > 10) && ((time() - $lastJob) > 10)) {
                        $logger->info(
                            'Inactivity detected, restarting',
                            [
                                'runtime' => time() - $bootTime,
                                'jobs'    => $jobCount
                            ]
                        );
                        exit;
                    }

                    continue;
                }

                $serverFailure = 0;
            }
        }

        if ($gearman->returnCode() != \GEARMAN_SUCCESS) {
            $logger->error($gearman->error());
        }

        $logger->debug('Leaving Gearman Worker Loop', ['runtime' => time() - $bootTime, 'jobs' => $jobCount]);
    }
}
