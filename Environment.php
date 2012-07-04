<?php

namespace Git;

/**
 * Represents a set of Environment variables for use with git commands
 */
abstract class Environment
{
    protected $variables;
    protected $legal_variables = array();

    /**
     * Constructs an Environment.
     *
     * @param $variables array
     *  An optional array of variables with which to initialise the Environment.
     */
    public function __construct(array $variables = array())
    {
        $this->variables = array();
        $this->setAll($variables);
    }

    /**
     * Sets the value of a single variable in the Environment.
     *
     * @param $key string
     *  The name of the Environment variable.
     *
     * @param $value string
     *  The value of the Environment variable.
     *
     * @return Environment
     *  Returns $this for command chaining
     */
    public function set($key, $value)
    {
        if (!in_array($key, $this->legal_variables)) {
          throw new Exception\IllegalEnvironmentVariableException("'$key' is not a legal environment variable");
        }
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * Sets multiple Environment variables in one go.
     *
     * @param $variables array
     *  An array of Environment variables to set (key => value).
     *
     * @return Environment
     *  Returns $this for command chaining
     */
    public function setAll(array $variables)
    {
        foreach ($variables as $key => $value) {
            $this->variables[$key] = $value;
        }
        return $this;
    }

    /**
     * Returns the value of a given Environment variable.
     *
     * @param $key string
     *  The variable to return.
     *
     * @return string
     *  The raw environment variable value
     */
    public function get($key)
    {
        return isset($this->variables[$key]) ? $this->variables[$key] : NULL;
    }


    /**
     * Returns all the current Environment variables
     *
     * @return array
     *  The Environment variables
     */
    public function getAll()
    {
        return $this->variables;
    }

    /**
     * Clears one Environment variable.
     *
     * @param $key string
     *  The variable to clear.
     *
     * @return Environment
     *  Returns $this for command chaining
     */
    public function clear($key)
    {
        if (isset($this->variables[$key])) {
            unset($this->variables[$key]);
        }
        return $this;
    }

    /**
     * Clears multiple Environment variables in one go.
     *
     * @param $keys array (optional)
     *  An array of the keys to clear.
     *  If not provided, or set to FALSE | NULL then all the variables will be cleared.
     *
     * @return Environment
     *  Returns $this for command chaining
     */
    public function clearAll($keys = NULL) {
        if (!is_array($keys) && empty($keys)) {
            $this->variables = array();
        }
        else {
            foreach ($keys as $key) {
                $this->clear($key);
            }
        }
        return $this;
    }

    /**
     * Returns the number of variables in the Environment.
     *
     * @return int
     *  The number of variables in the Environment.
     */
    public function count() {
        return count($this->variables);
    }

    /**
     * Returns a properly escaped string of arguments suitable for use in a git command on the shell.
     *
     * @return string
     *  The escaped string of arguments.
     */
    public function __toString()
    {
        $args = array();
        foreach ($this->variables as $key => $value) {
          $args[] = $key . '=' . escapeshellarg($value);
        }
        $args_string = implode(' ', $args);
        return $args_string;
    }
}