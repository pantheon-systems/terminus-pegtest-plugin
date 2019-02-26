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
    protected $valid_frameworks = ['drupal', 'drupal8', 'wordpress'];

    /**
     * @inheritdoc
     */
    protected $unavailable_commands = [];

    /**
     * sprintf templates for the command to run for a given CLI.
     *
     * @var array
     */
    protected $scriptTemplates = [
        'drush' => 'scr %s --script-path=../files',
        'wp' => 'eval-file ../files/%s',
    ];

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
        list($site, $environment) = $this->getSiteEnv($site_env_id);
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
     * @option url The URL to use when running the cURL test
     * @option constant-name The constant name to use when running the cURL test
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

        $results = $this->runTest($site_env_id, 'curltest.php', $options);

        if (!empty($results['results'])) {
            $this->log()->notice('cURL test completed successfully; PEG is configured properly.');
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
     */
    public function ldapTestComand(
        $site_env_id,
        $options = [
            'constant-name' => null,
            'use-tls' => 'true',
            'proto' => 3,
            'bind-dn' => null,
            'bind-password' => null,
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

        $results = $this->runTest($site_env_id, 'ldaptest.php', $options);

        if (!empty($results['results'])) {
            $this->log()->notice('LDAP test completed succesfully; PEG is configured properly.');
            $this->log()->info($results['results']);
            $this->log()->info('Elapsed time (sec): ' . $results['elapsed']);
        } else {
            $this->log()->error('LDAP test completed unsuccessfully. Error was: ' . $results['error']);
        }
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
     * @option constant-name The constant name to use when running the cURL test
     */
    public function simpleSshTestCommand(
        $site_env_id,
        $options = ['constant-name' => null]
    ) {
        // Validate the options.
        if (empty($options['constant-name'])) {
            throw new TerminusException('The {constant-name} option must be specified.');
        }

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
     * Run an authenticated SSH test to ensure PEG setup for complex SSH requests is working properly.
     *
     * @authorize
     *
     * @command peg:test:ssh-auth
     * @aliases ptssh-auth
     *
     * @param string $site_env_id Name of the environment to run the command on.
     */
    public function authenticatedSshTestCommand($site_env_id)
    {
        $this->log()->error('Unable to run this command via Terminus. Download the peg_test module or plugin for your framework to run this test.');
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
     * Wrapper for PHP's passthru command.
     *
     * @param string $command The command to run.
     */
    protected function passthru($command)
    {
        $result = 0;
        \passthru($command, $result);

        if ($result != 0) {
            throw new TerminusException('Command `{command}` failed with exit code {status}', [
                'command' => $command,
                'status' => $result
            ]);
        }
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
        list($site, $environment) = $this->getSiteEnv($site_env_id);
        $envID = $environment->getName();
        $siteID = $site->get('id');
        $siteAddress = "$envID.$siteID@appserver.$envID.$siteID.drush.in";
        $tmp = \sys_get_temp_dir();
        $testFilename = "$tmp/$filename";
        $framework = $site->get('framework');

        // Generate the test file based on command line options, then push to the site.
        $template = \file_get_contents(__DIR__ . "/../Resources/Templates/$filename");
        foreach ($options as $k => $v) {
            $template = str_replace("%$k%", \htmlspecialchars($v), $template);
        }
        \file_put_contents($testFilename, $template);
        $this->rsync($testFilename, "$siteAddress:files");

        switch ($framework) {
            case 'drupal':
            case 'drupal8':
                $this->command = 'drush';
                break;
            case 'wordpress':
                $this->command = 'wp';
                break;
        }

        if (empty($this->command)) {
            throw new TerminusException('Cannot determine whether to use drush or wp-cli.');
        }

        // Run the test.
        $toExecute = sprintf($this->scriptTemplates[$this->command], $filename);
        $toExecute = explode(' ', $toExecute);
        $this->prepareEnvironment($site_env_id);
        $this->executeCommand($toExecute);

        // Rsync the results file back down to the local machine.
        $resultsFilename = "$tmp/testresults.json";
        $serverResultsFilename = pathinfo($filename)['filename'] . '_results.json';
        $this->rsync("$siteAddress:files/$serverResultsFilename", $resultsFilename);

        if (!\file_exists($resultsFilename)) {
            throw new TerminusException('Unable to locate results file.');
        }

        $resultsRaw = \file_get_contents($resultsFilename);
        $results = \json_decode($resultsRaw, true);
        \unlink($resultsFilename);
        return $results;
    }
}
