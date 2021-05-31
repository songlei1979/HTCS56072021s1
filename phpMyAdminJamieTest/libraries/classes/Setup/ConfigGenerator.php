<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Config file generator
 *
 * @package PhpMyAdmin-Setup
 */
declare(strict_types=1);

namespace PhpMyAdmin\Setup;

use PhpMyAdmin\Config\ConfigFile;

/**
 * Config file generation class
 *
 * @package PhpMyAdmin
 */
class ConfigGenerator
{
    /**
     * Creates config file
     *
     * @param ConfigFile $cf Config file instance
     *
     * @return string
     */
    public static function getConfigFile(ConfigFile $cf)
    {
        $crlf = (isset($_SESSION['eol']) && $_SESSION['eol'] == 'win')
            ? "\r\n"
            : "\n";
        $conf = $cf->getConfig();

        // header
        $ret = '<?php' . $crlf
            . '/*' . $crlf
            . ' * Generated configuration file' . $crlf
            . ' * Generated by: phpMyAdminLei '
                . $GLOBALS['PMA_Config']->get('PMA_VERSION')
                . ' setup script' . $crlf
            . ' * Date: ' . gmdate(DATE_RFC1123) . $crlf
            . ' */' . $crlf . $crlf;

        //servers
        if (! empty($conf['Servers'])) {
            $ret .= self::getServerPart($cf, $crlf, $conf['Servers']);
            unset($conf['Servers']);
        }

        // other settings
        $persistKeys = $cf->getPersistKeysMap();

        foreach ($conf as $k => $v) {
            $k = preg_replace('/[^A-Za-z0-9_]/', '_', $k);
            $ret .= self::_getVarExport($k, $v, $crlf);
            if (isset($persistKeys[$k])) {
                unset($persistKeys[$k]);
            }
        }
        // keep 1d array keys which are present in $persist_keys (config.values.php)
        foreach (array_keys($persistKeys) as $k) {
            if (mb_strpos($k, '/') === false) {
                $k = preg_replace('/[^A-Za-z0-9_]/', '_', $k);
                $ret .= self::_getVarExport($k, $cf->getDefault($k), $crlf);
            }
        }
        $ret .= '?' . '>';

        return $ret;
    }

    /**
     * Returns exported configuration variable
     *
     * @param string $var_name  configuration name
     * @param mixed  $var_value configuration value(s)
     * @param string $crlf      line ending
     *
     * @return string
     */
    private static function _getVarExport($var_name, $var_value, $crlf)
    {
        if (! is_array($var_value) || empty($var_value)) {
            return "\$cfg['$var_name'] = "
                . var_export($var_value, true) . ';' . $crlf;
        }
        $ret = '';
        if (self::_isZeroBasedArray($var_value)) {
            $ret = "\$cfg['$var_name'] = "
                . self::_exportZeroBasedArray($var_value, $crlf)
                . ';' . $crlf;
        } else {
            // string keys: $cfg[key][subkey] = value
            foreach ($var_value as $k => $v) {
                $k = preg_replace('/[^A-Za-z0-9_]/', '_', $k);
                $ret .= "\$cfg['$var_name']['$k'] = "
                    . var_export($v, true) . ';' . $crlf;
            }
        }
        return $ret;
    }

    /**
     * Check whether $array is a continuous 0-based array
     *
     * @param array $array Array to check
     *
     * @return boolean
     */
    private static function _isZeroBasedArray(array $array)
    {
        for ($i = 0, $nb = count($array); $i < $nb; $i++) {
            if (! isset($array[$i])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Exports continuous 0-based array
     *
     * @param array  $array Array to export
     * @param string $crlf  Newline string
     *
     * @return string
     */
    private static function _exportZeroBasedArray(array $array, $crlf)
    {
        $retv = [];
        foreach ($array as $v) {
            $retv[] = var_export($v, true);
        }
        $ret = "array(";
        if (count($retv) <= 4) {
            // up to 4 values - one line
            $ret .= implode(', ', $retv);
        } else {
            // more than 4 values - value per line
            $imax = count($retv);
            for ($i = 0; $i < $imax; $i++) {
                $ret .= ($i > 0 ? ',' : '') . $crlf . '    ' . $retv[$i];
            }
        }
        $ret .= ')';
        return $ret;
    }

    /**
     * Generate server part of config file
     *
     * @param ConfigFile $cf      Config file
     * @param string     $crlf    Carriage return char
     * @param array      $servers Servers list
     *
     * @return string|null
     */
    protected static function getServerPart(ConfigFile $cf, $crlf, array $servers)
    {
        if ($cf->getServerCount() === 0) {
            return null;
        }

        $ret = "/* Servers configuration */$crlf\$i = 0;" . $crlf . $crlf;
        foreach ($servers as $id => $server) {
            $ret .= '/* Server: '
                . strtr($cf->getServerName($id) . " [$id] ", '*/', '-')
                . "*/" . $crlf
                . '$i++;' . $crlf;
            foreach ($server as $k => $v) {
                $k = preg_replace('/[^A-Za-z0-9_]/', '_', $k);
                $ret .= "\$cfg['Servers'][\$i]['$k'] = "
                    . (is_array($v) && self::_isZeroBasedArray($v)
                        ? self::_exportZeroBasedArray($v, $crlf)
                        : var_export($v, true))
                    . ';' . $crlf;
            }
            $ret .= $crlf;
        }
        $ret .= '/* End of servers configuration */' . $crlf . $crlf;
        return $ret;
    }
}
