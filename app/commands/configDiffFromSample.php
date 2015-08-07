<?php
use Illuminate\Console\Command;

/**
 * Diffs configs vs sample to catch anything added in sample that is not in current config.
 */
class configDiffFromSample extends Command
{
    protected $name = "config:diff-from-sample";
    protected $description = "Show keys in sample config that are not in current config";

    public function fire()
    {
        $config_files = ['app.php', 'orbit.php'];
        foreach ($config_files as $config_file) {
            $sample_config = require(app_path('config/' . $config_file . '.sample'));
            $current_config = require(app_path('config/' . $config_file));
            $this->recursiveDiff($config_file, '', $sample_config, $current_config);
        }
    }

    private function isKeyValueArray($array)
    {
        $keys = array_keys($array);
        foreach ($keys as $k) {
            if (!is_int($k)) {
                return true;
            }
        }
        return false;
    }

    private function recursiveDiff($file, $key_path, $sample_config, $current_config)
    {
        // $this->comment("Checking {$file} {$key_path}");
        $keys_not_in_current = array_diff_key($sample_config, $current_config);
        foreach ($keys_not_in_current as $key => $value_ignored) {
            $this->error("Config '{$key_path}{$key}' not in current {$file}");
        }
        $keys_in_both = array_intersect_key($sample_config, $current_config);
        foreach ($keys_in_both as $key => $value_ignored) {
            $sample_value = $sample_config[$key];
            $current_value = $current_config[$key];
            if (!is_array($sample_value)) {
                continue;
            }
            if ($this->isKeyValueArray($sample_value)) {
                $this->recursiveDiff($file, $key_path . $key . '.', $sample_value, $current_value);
            } else {
                // just compare counts
                $sample_count = count($sample_value);
                $current_count = count($current_value);
                if ($sample_count != $current_count) {
                    $this->error("Config '{$key_path}{$key}' in {$file} different element counts! (sample: {$sample_count} current: {$current_count})");
                }
            }
        }
    }
}