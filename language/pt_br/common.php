<?php
/**
 * Arquivo de idioma comum do AntiSpam Guard.
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

$lang = array_merge($lang, array(
    'ANTISPAMGUARD_HP_LABEL'     => 'Deixe este campo vazio',
    'ANTISPAMGUARD_BLOCKED'      => 'O envio não pôde ser aceito.',
    'ANTISPAMGUARD_BLOCKED_GENERIC' => 'O envio não pôde ser aceito. Revise as informações e tente novamente.',
    'ANTISPAMGUARD_BLOCKED_CONTENT' => 'O envio não pôde ser aceito por conter conteúdo suspeito.',
    'ANTISPAMGUARD_BLOCKED_TIME' => 'O envio não pôde ser aceito neste momento.',
    'ANTISPAMGUARD_BLOCKED_RATE_LIMIT' => 'Muitas tentativas foram detectadas a partir do seu IP. Aguarde alguns minutos e tente novamente.',
    'ANTISPAMGUARD_BLOCKED_IP' => 'O envio não pôde ser aceito a partir deste IP.',
    'ANTISPAMGUARD_BLOCKED_SFS' => 'Sua ação foi bloqueada por reputação anti-spam externa.',
    'ANTISPAMGUARD_REGISTER_NOTICE_DEFAULT' => 'Este fórum usa proteção antispam automática para reduzir cadastros abusivos e proteger a comunidade.',
));
