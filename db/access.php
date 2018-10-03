<?php


defined('MOODLE_INTERNAL') || die;

$capabilities = array(
    'local/userenrols:access' => array(
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
        )
    ),
 );
