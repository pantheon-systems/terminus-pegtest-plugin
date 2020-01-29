<?php

namespace Pantheon\TerminusPegTest\Commands;

use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Commands\Remote\SSHBaseCommand;
use Pantheon\Terminus\Exceptions\TerminusProcessException;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class PegCommand extends SSHBaseCommand
{
    /**
     * @inheritdoc
     */
    protected $command = '';

    /**
     * @inheritdoc
     */
    protected $valid_frameworks = ['drupal', 'drupal8', 'wordpress', 'wordpress_network'];

    /**
     * @inheritdoc
     */
    protected $unavailable_commands = [];

    /**
     * The Site ID.
     *
     * @var string
     */
    protected $siteID = '';

    /**
     * The Environment ID.
     *
     * @var string
     */
    protected $envID = '';

    /**
     * The login and FQDN to the site and environment.
     *
     * @var string
     */
    protected $siteaddress = '';

    /**
     * The site framework.
     *
     * @var string
     */
    protected $framework = '';

    /**
     * sprintf templates for the command to run for a given CLI.
     *
     * @var array
     */
    protected $scriptTemplates = [
        'drush' => 'scr %script-name% --script-path=%script-path%',
        'wp' => 'eval-file %script-path%/%script-name%',
    ];

    /**
     * Get some commonly-used information when a command first runs.
     *
     * @param string $site_env_id Name of the environment to run the drush command on.
     */
    protected function baseCommand($site_env_id)
    {
        list($site, $environment) = $this->getSiteEnv($site_env_id);
        $this->envID = $environment->getName();
        $this->siteID = $site->get('id');
        $this->siteAddress = "{$this->envID}.{$this->siteID}@appserver.{$this->envID}.{$this->siteID}.drush.in";
        $this->framework = $site->get('framework');
        return [$site, $environment];
    }

    /**
     * Get a list of PEG constants.
     *
     * @authorize
     *
     * @command peg:constants:list
     * @aliases pegs
     *
     * @field-labels
     *     php_constant_name: Constant Name
     *     target_ip: Target IP
     *     target_port: Target Port
     * @return RowsOfFields
     *
     * @param string $site_env_id Name of the environment to run the drush command on.
     */
    public function constantsListCommand($site_env_id)
    {
        list($site, $environment) = $this->baseCommand($site_env_id);
        if ($stunnels = $site->get('stunnel')) {
            $stunnels = (array)$stunnels;
            $constants = array_map(function ($stunnelName, $stunnelDetails) {
                return [
                    'php_constant_name' => "PANTHEON_SOIP_$stunnelName",
                    'target_ip' => $stunnelDetails->target_ip,
                    'target_port' => $stunnelDetails->target_port,
                ];
            }, array_keys($stunnels), $stunnels);

            return new RowsOfFields($constants);
        } else {
            $this->log()->notice('There are no PEG constants configured for this environment.');
        }
    }

    /**
     * Run a cURL test to ensure PEG setup for basic HTTP requests is working properly.
     *
     * @authorize
     *
     * @command peg:test:curl
     * @aliases ptcurl
     *
     * @param string $site_env_id Name of the environment to run the command on.
     * @option url The URL to use when running the cURL test.
     * @option constant-name The constant name to use when running the cURL test.
     */
    public function curlTestCommand(
        $site_env_id,
        $options = [
            'url' => null,
            'constant-name' => null,
        ]
    ) {
        // Validate the options.
        if (empty($options['url'])) {
            throw new TerminusException('The {url} option must be specified.');
        }
        if (empty($options['constant-name'])) {
            throw new TerminusException('The {constant-name} option must be specified.');
        }

        $this->baseCommand($site_env_id);
        $results = $this->runTest($site_env_id, 'curltest.php', $options);

        if (!empty($results['results'])) {
            $this->log()->success('cURL test completed successfully; PEG is configured properly.');
            $this->log()->info($results['results']);
            $this->log()->info('Elapsed time (sec): ' . $results['elapsed']);
        } else {
            $this->log()->error('cURL test completed unsuccessfully. Error was: ' . $results['error']);
        }
    }

    /**
     * Run an LDAP test to ensure PEG setup for basic LDAP requests is working properly.
     *
     * @authorize
     *
     * @command peg:test:ldap
     * @aliases ptldap
     *
     * @param string $site_env_id Name of the environment to run the command on.
     * @option constant-name The constant name to use when running the cURL test
     * @option use-tls Whether or not to use TLS (TRUE/FALSE)
     * @option proto The LDAP protocol to use (2/3)
     * @option bind-dn The bind DN to use when connecting to the LDAP server. Leave blank to perform an anonymous binding.
     * @option bind-password The bind password to use when connecting to the LDAP server.
     * @option bypass-tls-check Bypass TLS certificate validation. The drupal LDAP module doesn't have this set but it is a useful debugging option. (TRUE/FALSE)
     */
    public function ldapTestComand(
        $site_env_id,
        $options = [
            'constant-name' => null,
            'use-tls' => 'true',
            'proto' => 3,
            'bind-dn' => null,
            'bind-password' => null,
            'bypass-tls-check' => 'false',
        ]
    ) {
        // Validate the options.
        if (empty($options['constant-name'])) {
            throw new TerminusException('The {constant-name} option must be specified.');
        }
        
        // If just the bind-password option was specified without a value, prompt for it.
        if ($options['bind-dn'] && \is_bool($options['bind-password'])) {
            $userBindPW = $this->io()->askHidden('Please enter a bind password');
            $options['bind-password'] = \trim($userBindPW);
        }

        // Ensure the template will still create properly.
        if ($options['bind-dn'] === null) {
            $options['bind-dn'] = '';
        }

        $this->baseCommand($site_env_id);
        $results = $this->runTest($site_env_id, 'ldaptest.php', $options);

        if (!empty($results['results'])) {
            $this->log()->success('LDAP test completed succesfully; PEG is configured properly.');
            $this->log()->info($results['results']);
            $this->log()->info('Elapsed time (sec): ' . $results['elapsed']);
        } else {
            $this->log()->error('LDAP test completed unsuccessfully. Error was: ' . $results['error']);
        }
    }

    /**
     * Run an SMTP test to ensure PEG setup for SMTP requests is working properly.
     *
     * @authorize
     *
     * @command peg:test:smtp
     * @aliases ptsmtp
     *
     * @param string $site_env_id Name of the environment to run the command on.
     * @option constant-name The constant name to use when running the cURL test.
     * @option relay-address The address of the mail server to use an SMTP relay.
     */
    public function smtpTestCommand(
        $site_env_id,
        $options = [
            'constant-name' => null,
            'relay-address' => null,
        ]
    ) {
        // Validate the options.
        if (empty($options['constant-name'])) {
            throw new TerminusException('The {constant-name} option must be specified.');
        }
        if (empty($options['relay-address'])) {
            throw new TerminusException('The {relay-address} option must be specified.');
        }

        $this->baseCommand($site_env_id);
        $results = $this->runTest($site_env_id, 'smtptest.php', $options);

        if (!empty($results['results'])) {
            $this->log()->success('SMTP test completed successfully; PEG is configured properly.');
            $this->log()->info($results['results']);
            $this->log()->info('Elapsed time (sec): ' . $results['elapsed']);
        } else {
            $this->log()->error('SMTP test completed unsuccessfully. Error was: ' . $results['error']);
        }
    }

    /**
     * Run an OpenSSL cert check.
     *
     * @authorize
     *
     * @command peg:showcerts
     * @aliases ptcerts
     *
     * @param string $site_env_id Name of the environment to run the command on.
     * @option constant-name The constant name to use when running the cURL test.
     * @option proto The specific protocol to test (currently supports smtp, pop3, imap, ftp, and xmpp).
     */
    public function showCertsCommand(
        $site_env_id,
        $options = [ 'constant-name' => null, 'proto' => null ]
    ) {
        // Validate the options.
        if (empty($options['constant-name'])) {
            throw new TerminusException('The {constant-name} option must be specified.');
        }

        // Protocol is optional but if you're testing an FTP or SMTP server you
        // might want to include that.
        if (empty($options['proto'])) {
            // Convert null values to an empty string.
            $options['proto'] = '';
        } else {
            $supportedProtos = ['smtp', 'pop3', 'imap', 'ftp', 'xmpp'];
            $options['proto'] = \strtolower($options['proto']);
            if (\array_search($options['proto'], $supportedProtos) === false) {
                throw new TerminusException('The {proto} option must be one of the following: ' . \implode(', ', $supportedProtos));
            }
        }

        $this->baseCommand($site_env_id);
        $results = $this->runTest($site_env_id, 'showcerts.php', $options);

        $this->log()->notice($results['results']);
        $this->log()->info('Elapsed time (sec): ' . $results['elapsed']);
    }

    /**
     * Run a simple SSH test to ensure PEG setup for basic SSH requests is working properly.
     *
     * @authorize
     *
     * @command peg:test:ssh
     * @aliases ptssh
     *
     * @param string $site_env_id Name of the environment to run the command on.
     * @option constant-name The constant name to use when running the cURL test.
     */
    public function simpleSshTestCommand(
        $site_env_id,
        $options = ['constant-name' => null]
    ) {
        // Validate the options.
        if (empty($options['constant-name'])) {
            throw new TerminusException('The {constant-name} option must be specified.');
        }

        $this->baseCommand($site_env_id);
        $results = $this->runTest($site_env_id, 'sshtest.php', $options);

        if (!empty($results['results'])) {
            $this->log()->notice('Simple SSH test completed succesfully; PEG is configured properly.');
            $this->log()->info($results['results']);
            $this->log()->info('Elapsed time (sec): ' . $results['elapsed']);
        } else {
            $this->log()->error('Simple SSH test completed unsuccessfully. Error was: ' . $results['error']);
        }
    }

    /**
     * Rsync a file from a source to a destination.
     *
     * @param string $src The source of the file to rsync.
     * @param string $dest The destination to send the file to.
     */
    protected function rsync($src, $dest)
    {
        $this->passthru("rsync -rlIpz --ipv4 -e 'ssh -p 2222' $src $dest");
    }
    
    /**
     * Use SFTP to put a file in a place.
     *
     * @param string $localFilePath The local path to the file
     * @param string $destPath The destination path on the server.
     */
    protected function putFile($localFilePath, $destPath = 'files/')
    {
        $sftpCommand = "sftp -o Port=2222 {$this->siteAddress} <<< $'put $localFilePath $destPath'";
        $this->passthru($sftpCommand);
    }

    /**
     * Use SFTP to get a remote file.
     *
     * @param string $serverPath The path to the file on the server.
     * @param string $localFilePath The path to store the file in.
     */
    protected function getFile($serverPath, $localFilePath)
    {
        $sftpCommand = "sftp -o Port=2222 {$this->siteAddress}:$serverPath $localFilePath";
        $this->passthru($sftpCommand);
    }

    /**
     * Use SFTP to remove a remote file.
     *
     * @param string $serverPath The path of the file on the server.
     */
    protected function deleteFile($serverPath)
    {
        $sftpCommand = "sftp -o Port=2222 {$this->siteAddress} <<< $'rm $serverPath'";
        $this->passthru($sftpCommand);
    }

    /**
     * Hacky way to get the binding path on the server using SFTP.
     *
     * @return string The binding path of the environment passed to the terminus command.
     */
    protected function getBindingPath()
    {
        $sftpCommand = "sftp -o Port=2222 {$this->siteAddress} <<< $'pwd'";
        $results = $this->passthru($sftpCommand);
        preg_match('/: (.*)$/', $results, $matches);
        return $matches[1];
    }

    /**
     * Wrapper for PHP's passthru command.
     *
     * @param string $command The command to run.
     * @return string The output of the command.
     */
    protected function passthru($command, $quiet = true)
    {
        $result = 0;
        $output = 'Use "quiet=true" to enable output buffering/return';
        if ($quiet) \ob_start();
        \passthru($command, $result);
        if ($quiet) {
            $output = \ob_get_contents();
            \ob_end_clean();
            $this->log()->debug($output);
        }
        if ($result != 0) {
            throw new TerminusException('Command `{command}` failed with exit code {status}', [
                'command' => $command,
                'status' => $result
            ]);
        }

        return $output;
    }

    /**
     * Execute the test.
     *
     * @param string $site_env_id The site and environment from the command line.
     * @param string $filename The base name of the test template.
     * @param array $options The options from the command line.
     *
     * @return array The results of the test.
     */
    protected function runTest($site_env_id, $filename, array $options)
    {
        $tmp = \sys_get_temp_dir();
        $testFilename = "$tmp/$filename";

        // Generate the test file based on command line options, then push to the site.
        $template = \file_get_contents(__DIR__ . "/../Resources/Templates/$filename");
        foreach ($options as $k => $v) {
            $template = str_replace("%$k%", \htmlspecialchars($v), $template);
        }
        \file_put_contents($testFilename, $template);
        $this->rsync($testFilename, "{$this->siteAddress}:files");

        switch ($this->framework) {
            case 'drupal':
            case 'drupal8':
                $this->command = 'drush';
                break;
            case 'wordpress':
            case 'wordpress_network':
                $this->command = 'wp';
                break;
        }

        if (empty($this->command)) {
            throw new TerminusException('Cannot determine whether to use drush or wp-cli.');
        }

        // Run the test.
        $filesPath = $this->getBindingPath() . '/files';
        $baseCommand = $this->scriptTemplates[$this->command];
        $toExecute = \str_replace('%script-path%', $filesPath, $baseCommand);
        $toExecute = \str_replace('%script-name%', $filename, $toExecute);
        $toExecute = explode(' ', $toExecute);
        $this->prepareEnvironment($site_env_id);
        $this->executeCommand($toExecute);

        // Copy the results file back down to the local machine, then clean up.
        $resultsFilename = "$tmp/testresults.json";
        $serverResultsFilename = pathinfo($filename)['filename'] . '_results.json';
        $this->rsync("{$this->siteAddress}:files/$serverResultsFilename", $resultsFilename);
        $this->deleteFile("files/$serverResultsFilename");
        $this->deleteFile("files/$filename");


        if (!\file_exists($resultsFilename)) {
            throw new TerminusException('Unable to locate results file.');
        }

        $resultsRaw = \file_get_contents($resultsFilename);
        $results = \json_decode($resultsRaw, true);
        \unlink($resultsFilename);
        return $results;
    }
}
