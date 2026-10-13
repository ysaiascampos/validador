<?php

namespace Vendor\Validator\Validators;

use Vendor\Validator\Contracts\ValidatorInterface;
use Vendor\Validator\DTO\ValidationResult;

class GitValidator implements ValidatorInterface
{
    /**
     * The base path to the git repository.
     *
     * @var string
     */
    private $repoPath;

    /**
     * Validation configuration.
     *
     * @var array
     */
    private $config;

    /**
     * Cached git command output.
     *
     * @var array
     */
    private $cache;

    /**
     * Constructor.
     *
     * @param string $repoPath
     * @param array $config
     */
    public function __construct($repoPath = '', array $config = array())
    {
        if (!empty($repoPath)) {
            $this->repoPath = $repoPath;
        } elseif (function_exists('base_path')) {
            $this->repoPath = base_path();
        } else {
            $this->repoPath = getcwd();
        }

        $this->config = $config;
        $this->cache = array();
    }

    /**
     * Get the validator name.
     *
     * @return string
     */
    public function getName()
    {
        return 'git';
    }

    /**
     * Execute git validation.
     *
     * @param array $options
     * @return ValidationResult
     */
    public function validate(array $options = array())
    {
        $startTime = microtime(true);
        $result = new ValidationResult($this->getName());

        try {
            $branch = $this->getCurrentBranch();
            $commit = $this->getCurrentCommit();

            if ($branch === false) {
                $result->addError('Unable to determine current git branch');
            } else {
                $result->addDetail('branch', $branch);
            }

            if ($commit === false) {
                $result->addError('Unable to determine current git commit hash');
            } else {
                $result->addDetail('commit', $commit);
            }

        } catch (\Exception $e) {
            $result->addError(sprintf('Git validation error: %s', $e->getMessage()));
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $result->setExecutionTime($executionTime);

        return $result;
    }

    /**
     * Get the current branch name.
     *
     * @return string|false
     */
    private function getCurrentBranch()
    {
        if (isset($this->cache['branch'])) {
            return $this->cache['branch'];
        }

        $output = $this->execGitCommand('git rev-parse --abbrev-ref HEAD 2>&1');
        $output = trim($output);

        if (empty($output) || $output === 'HEAD') {
            $this->cache['branch'] = false;
            return false;
        }

        $this->cache['branch'] = $output;
        return $output;
    }

    /**
     * Get the current commit hash.
     *
     * @return string|false
     */
    private function getCurrentCommit()
    {
        if (isset($this->cache['commit'])) {
            return $this->cache['commit'];
        }

        $output = $this->execGitCommand('git rev-parse HEAD 2>&1');
        $output = trim($output);

        if (empty($output) || preg_match('/^fatal:/i', $output)) {
            $this->cache['commit'] = false;
            return false;
        }

        $this->cache['commit'] = $output;
        return $output;
    }

    /**
     * Execute a git command.
     *
     * @param string $command
     * @return string
     */
    private function execGitCommand($command)
    {
        $descriptorspec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $process = proc_open(
            $command,
            $descriptorspec,
            $pipes,
            $this->repoPath
        );

        if (!is_resource($process)) {
            return '';
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        if ($returnCode !== 0) {
            return trim($stderr);
        }

        return trim($stdout);
    }
}
