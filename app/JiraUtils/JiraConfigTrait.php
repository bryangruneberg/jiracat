<?php namespace App\JiraUtils;

use Symfony\Component\Yaml\Yaml;

trait JiraConfigTrait
{
    private static function get_config_file_extension()
    {
        return ".yml";
    }

    public static function resolveConfigArray($fileName = NULL)
    {

        if(!$fileName) {
            $fileName = (app()->environment()).'_jiracat';
        }

        $config = [];
        $found = FALSE;

        if(file_exists(getcwd() . DIRECTORY_SEPARATOR . $fileName . self::get_config_file_extension())) {
            $config = self::array_merge_recursive_distinct($config, Yaml::parse(file_get_contents(getcwd() . DIRECTORY_SEPARATOR . $fileName . self::get_config_file_extension())));
            $config['ymlfiles'][] = getcwd() . DIRECTORY_SEPARATOR . $fileName . self::get_config_file_extension();
            $found = TRUE;
        }

        if(file_exists(self::getHomeDirectory() . DIRECTORY_SEPARATOR . $fileName . self::get_config_file_extension())) {
            $config = self::array_merge_recursive_distinct($config, Yaml::parse(file_get_contents( self::getHomeDirectory() . DIRECTORY_SEPARATOR . $fileName . self::get_config_file_extension())));
            $config['ymlfiles'][] = self::getHomeDirectory() . DIRECTORY_SEPARATOR . $fileName . self::get_config_file_extension();
            $found = TRUE;
        }


        if(file_exists(base_path() . DIRECTORY_SEPARATOR . $fileName . self::get_config_file_extension())) {
            $config = self::array_merge_recursive_distinct($config, Yaml::parse(file_get_contents(base_path() . DIRECTORY_SEPARATOR . $fileName . self::get_config_file_extension())));
            $config['ymlfiles'][] = base_path() . DIRECTORY_SEPARATOR . $fileName . self::get_config_file_extension();
            $found = TRUE;
        }

        if(!$found && $fileName != 'jiracat') {
            return self::resolveConfigArray('jiracat');
        }

        return $config;
    }

    public static function getHomeDirectory()
    {
        return $_SERVER['HOME'];
    }

    /**
     * array_merge_recursive does indeed merge arrays, but it converts values
     * with duplicate keys to arrays rather than overwriting the value in the
     * first array with the duplicate value in the second array, as array_merge
     * does. I.e., with array_merge_recursive, this happens (documented
     * behavior):
     *
     * array_merge_recursive(array('key' => 'org value'), array('key' => 'new
     * value'));
     *     => array('key' => array('org value', 'new value'));
     *
     * array_merge_recursive_distinct does not change the datatypes of the
     * values in the arrays. Matching keys' values in the second array
     * overwrite those in the first array, as is the case with array_merge,
     * i.e.:
     *
     * array_merge_recursive_distinct(array('key' => 'org value'), array('key'
     * => 'new value'));
     *     => array('key' => array('new value'));
     *
     * Parameters are passed by reference, though only for performance reasons.
     * They're not altered by this function.
     *
     * @param array $array1
     * @param array $array2
     *
     * @return array
     * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
     * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
     */
    public static function array_merge_recursive_distinct(
      array $array1,
      array $array2
    ) {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset ($merged [$key]) && is_array($merged [$key])) {
                $merged [$key] = self::array_merge_recursive_distinct($merged [$key],
                  $value);
            } else {
                $merged [$key] = $value;
            }
        }

        return $merged;
    }
}
