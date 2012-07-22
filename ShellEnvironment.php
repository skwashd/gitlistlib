<?php

namespace Git;

/**
 * Represents a set of Environment variables for use with git commands
 */
class ShellEnvironment extends Environment
{
    protected $legal_variables = array(
        'SSH_ASKPASS',
        'DISPLAY',
        'SSH_PASS',
    );
}
