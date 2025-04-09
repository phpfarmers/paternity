<?php

use Illuminate\Database\Eloquent\Collection;

if (!function_exists('array_get')) {
    /**
     * 从数组中获取值，支持点号语法
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function array_get($array, $key, $default = null)
    {
        if (is_null($key)) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('str_slug')) {
    /**
     * 将字符串转换为URL友好的slug
     *
     * @param string $title
     * @param string $separator
     * @return string
     */
    function str_slug($title, $separator = '-')
    {
        // 转换为小写
        $title = mb_strtolower($title, 'UTF-8');

        // 替换特殊字符
        $title = preg_replace('/[^a-z0-9ก-๙]+/u', $separator, $title);

        // 去除多余的分隔符
        $title = preg_replace('/' . preg_quote($separator, '/') . '{2,}/', $separator, $title);

        // 去除首尾的分隔符
        $title = trim($title, $separator);

        return $title;
    }
}

if (!function_exists('is_json')) {
    /**
     * 检查字符串是否为有效的JSON
     *
     * @param string $string
     * @return bool
     */
    function is_json($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
