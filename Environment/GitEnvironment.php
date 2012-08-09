<?php

namespace Git\Environment;

/**
 * Represents a set of Environment variables for use with git commands
 */
class GitEnvironment extends Environment
{
    protected $legal_variables = array(
        'GIT_INDEX_FILE',
        'GIT_OBJECT_DIRECTORY',
        'GIT_ALTERNATE_OBJECT_DIRECTORIES',
        'GIT_DIR',
        'GIT_WORK_TREE',
        'GIT_CEILING_DIRECTORIES',
        'GIT_DISCOVERY_ACROSS_FILESYSTEM',
        'GIT_AUTHOR_NAME',
        'GIT_AUTHOR_EMAIL',
        'GIT_AUTHOR_DATE',
        'GIT_COMMITTER_NAME',
        'GIT_COMMITTER_EMAIL',
        'GIT_COMMITTER_DATE',
        'EMAIL',
        'GIT_DIFF_OPTS',
        'GIT_EXTERNAL_DIFF',
        'GIT_MERGE_VERBOSITY',
        'GIT_PAGER',
        'GIT_SSH',
        'GIT_ASKPASS',
        'GIT_FLUSH',
        'GIT_TRACE',
    );

    /**
     * Sets the value of a single variable in the Environment.
     *
     * @param $key string
     *  The name of the Environment variable.
     *
     * @param $value string
     *  The value of the Environment variable.
     *
     * @return Git\Environment\Environment
     *  Returns $this for command chaining
     */
    public function set($key, $value)
    {
        if (!in_array($key, $this->legal_variables)) {
          throw new \RuntimeException("'$key' is not a legal environment variable");
        }
        return parent::set($key, $value);
    }
}