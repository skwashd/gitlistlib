<?php

namespace Git;

use Silex\Application;

class Client
{
    protected $path;
    protected $hidden;
    protected $git_environment;
    protected $shell_environment;

    public function __construct($options = null)
    {
        $this->setPath($options['path']);
        $this->setHidden($options['hidden']);
        $this->git_environment = new Environment\GitEnvironment();
        $this->shell_environment = new Environment\ShellEnvironment();
    }

    /**
     * Creates a new repository on the specified path
     *
     * @param  string     $path Path where the new repository will be created
     * @return Repository Instance of Repository
     */
    public function createRepository($path)
    {
        if (file_exists($path . '/.git/HEAD') && !file_exists($path . '/HEAD')) {
            throw new \RuntimeException('A GIT repository already exists at ' . $path);
        }

        $repository = new Repository($path, $this);

        return $repository->create();
    }

    /**
     * Opens a repository at the specified path
     *
     * @param  string     $path Path where the repository is located
     * @return Repository Instance of Repository
     */
    public function getRepository($path)
    {
        $path = '/' . trim($path, '/');
        if (!file_exists($path)) {
            throw new \RuntimeException("Path '$path' does not exist");
        }

        if (in_array($path, $this->getHidden())) {
            throw new \RuntimeException('You don\'t have access to this repository');
        }

        $search_path = $path;
        // Traverse up the directory tree until we reach the root dir, or we find a git repository.
        while (!$this->pathContainsRepository($search_path) && $search_path != '') {
            $search_path = rtrim(dirname($search_path), '/');
        }

        if (!$this->pathContainsRepository($search_path)) {
           throw new \RuntimeException('There is no GIT repository at ' . $path);
        }

        // This check for hidden repos should be conducted elsewhere.
        // if (in_array($search_path, $this->app['hidden'])) {
        //  throw new \RuntimeException('You don\'t have access to this repository');
        // }

        return new Repository($search_path, $this);
    }

    /**
     * Clones a repository to a given path.
     *
     * @param string $url The URL of the repo to clone
     * @param string $path The file system path to which the repo should be cloned
     * @param array $options optional set of options to git.
     * @param array $args optional set of arguments to git.
     */
    public function cloneRepository($url, $directory, array $options = array(), array $args = array())
    {
        $repository = new Repository($directory, $this);
        array_unshift($args, $url, $directory);

        $this->run($repository, 'clone', $options, $args);
        return $repository;
    }

    /**
     * Looks for git repository in the given path.
     *
     * @return bool
     *  Whether the path contains a git repository.
     */
    protected function pathContainsRepository($path)
    {
        return file_exists($path . '/.git/HEAD') || file_exists($path . '/HEAD');
    }

    /**
     * Searches for valid repositories on the specified path
     *
     * @param  string $path Path where repositories will be searched
     * @return array  Found repositories, containing their name, path and description
     */
    public function getRepositories($path)
    {
        $repositories = $this->recurseDirectory($path);

        if (empty($repositories)) {
            throw new \RuntimeException('There are no GIT repositories in ' . $path);
        }

        sort($repositories);

        return $repositories;
    }

    private function recurseDirectory($path)
    {
        $dir = new \DirectoryIterator($path);

        $repositories = array();

        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }

            if (($pos = strrpos($file->getFilename(), '.')) === 0) {
                continue;
            }

            if ($file->isDir()) {
                $isBare = file_exists($file->getPathname() . '/HEAD');
                $isRepository = file_exists($file->getPathname() . '/.git/HEAD');

                if ($isRepository || $isBare) {
                    if (in_array($file->getPathname(), $this->getHidden())) {
                        continue;
                    }

                    if ($isBare) {
                        $description = $file->getPathname() . '/description';
                    } else {
                        $description = $file->getPathname() . '/.git/description';
                    }

                    if (file_exists($description)) {
                        $description = file_get_contents($description);
                    } else {
                        $description = 'There is no repository description file. Please, create one to remove this message.';
                    }

                    $repositories[] = array('name' => $file->getFilename(), 'path' => $file->getPathname(), 'description' => $description);
                    continue;
                }
            }
        }

        return $repositories;
    }

    /**
     * Execute a git command on the repository being manipulated
     *
     * This method will start a new process on the current machine and
     * run git commands. Once the command has been run, the method will
     * return the command line output.
     *
     * @param  Repository $repository Repository where the command will be run
     * @param  string     $command    Git command to be run
     * @return string     Returns the command output
     */
    public function run(Repository $repository, $command, $options = array(), $args = array())
    {
        $descriptors = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
        $prepared_command = $this->prepareCommand($command, $options, $args);
        $shell_env = $this->getShellEnvironment()->getAll() ?: array();
        $shell_env += $this->getGitEnvironment()->getAll() ?: array();
        $process = proc_open($prepared_command, $descriptors, $pipes, $repository->getPath(), $shell_env);

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to execute command: ' . $prepared_command);
        }

        $stderr = stream_get_contents($pipes[2]);
        $stdout = stream_get_contents($pipes[1]);

        $status = proc_get_status($process);
        $exit = $status['exitcode'];

        fclose($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[0]);
        proc_close($process);

        if (0 !== $exit) {
            throw new \RuntimeException("Error running command $prepared_command\n" . ($stderr ?: $stdout), $exit);
        }

        return $stdout;
    }

    /**
     * Sets the passphrase that will be used with the private key when communicating over SSH.
     *
     * @param string $passphrase The password to use.
     */
    public function setSSHPassphrase($passphrase)
    {
        if (empty($passphrase)) {
            $this->getShellEnvironment()->clearAll(array('SSH_ASKPASS', 'DISPLAY', 'SSH_PASS'));
        }
        else {
            $this->getShellEnvironment()->setAll(array(
                'SSH_ASKPASS' => __DIR__ . '/script/ssh-echopass',
                'DISPLAY' => 'hack',
                'SSH_PASS' => $passphrase,
            ));
        }
    }

    /**
     * Returns the client's git Environment.
     *
     * @return Git\Environment\GitEnvironment
     *  The client's git Environment object.
     */
    public function getGitEnvironment()
    {
        return $this->git_environment;
    }

    /**
     * Returns the client's git Environment.
     *
     * @return Git\Environment\ShellEnvironment
     *  The client's git Environment object.
     */
    public function getShellEnvironment()
    {
        return $this->shell_environment;
    }

    /**
     * Prepares a command for execution.
     *
     * @return string
     *  The prepared command.
     */
    protected function prepareCommand($command, array $options, array $args)
    {
        $command_parts = array();

        $command_parts[] = $this->getPath();
        $command_parts[] = $command;

        if (count($options) > 0) {
            $options_items = array();
            foreach ($options as $name => $value) {
                $options_item = $name;
                if (!is_null($value)) {
                    $options_item .= ' ' . escapeshellarg($value);
                }
                $options_items[] = $options_item;
            }
            $command_parts[] = implode(' ', $options_items);
        }

        if (count($args) > 0) {
            $command_parts[] = implode(' ', array_map('escapeshellarg', $args));
        }

        return implode(' ', $command_parts);
    }

    /**
     * Get the current Git binary path
     *
     * @return string Path where the Git binary is located
     */
    protected function getPath()
    {
        return $this->path;
    }

    /**
     * Set the current Git binary path
     *
     * @param string $path Path where the Git binary is located
     */
    protected function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Get hidden repository list
     *
     * @return array List of repositories to hide
     */
    protected function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Set the hidden repository list
     *
     * @param array $hidden List of repositories to hide
     */
    protected function setHidden($hidden)
    {
        $this->hidden = $hidden;
    }
}
