<?php
/**
 * AntiSpam Guard common language file.
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

$lang = array_merge($lang, array(
    'ANTISPAMGUARD_HP_LABEL'     => 'Laissez ce champ vide',
    'ANTISPAMGUARD_BLOCKED'      => 'Votre envoi n’a pas pu être accepté.',
    'ANTISPAMGUARD_BLOCKED_GENERIC' => 'Votre envoi n’a pas pu être accepté. Veuillez vérifier les informations et réessayer.',
    'ANTISPAMGUARD_BLOCKED_CONTENT' => 'L’envoi n’a pas pu être accepté car il contient du contenu suspect.',
    'ANTISPAMGUARD_BLOCKED_TIME' => 'Votre envoi ne peut pas être accepté pour le moment.',
    'ANTISPAMGUARD_BLOCKED_RATE_LIMIT' => 'Trop de tentatives ont été détectées depuis votre adresse IP. Veuillez patienter quelques minutes puis réessayer.',
    'ANTISPAMGUARD_BLOCKED_IP' => 'L’envoi n’a pas pu être accepté depuis cette adresse IP.',
    'ANTISPAMGUARD_BLOCKED_SFS' => 'Votre action a été bloquée par des vérifications externes de réputation anti-spam.',
    'ANTISPAMGUARD_REGISTER_NOTICE_DEFAULT' => 'Ce forum utilise une protection anti-spam automatique afin de réduire les inscriptions abusives et de protéger la communauté.',
));
