<?php

namespace SwiftPHP\Core\I18n;

/**
 * 国际化（i18n）支持类
 * 提供多语言翻译功能
 */
class I18n
{
    /**
     * 当前语言
     */
    protected static string $locale = 'zh-cn';

    /**
     * 默认语言
     */
    protected static string $defaultLocale = 'zh-cn';

    /**
     * 语言文件加载路径
     */
    protected static string $langPath = '';

    /**
     * 已加载的语言包
     */
    protected static array $loaded = [];

    /**
     * 语言列表缓存
     */
    protected static array $langList = [];

    /**
     * 是否启用回退到默认语言
     */
    protected static bool $fallback = true;

    /**
     * 是否已初始化
     */
    protected static bool $initialized = false;

    /**
     * 初始化国际化配置
     *
     * @param array $config 配置数组
     */
    public static function init(array $config = []): void
    {
        // 设置默认语言
        if (!empty($config['default_locale'])) {
            self::$defaultLocale = strtolower($config['default_locale']);
        }
        
        // 只有在首次初始化时才设置locale
        if (!self::$initialized) {
            if (!empty($config['locale'])) {
                self::$locale = strtolower($config['locale']);
            } else {
                self::$locale = self::$defaultLocale;
            }
        }
        self::$initialized = true;
        
        if (!empty($config['lang_path'])) {
            self::$langPath = rtrim($config['lang_path'], '/\\') . '/';
        } else {
            // 默认语言文件路径
            self::$langPath = dirname(__DIR__, 2) . '/app/lang/';
        }
        if (isset($config['fallback'])) {
            self::$fallback = (bool) $config['fallback'];
        }
        // 清除缓存
        self::clearCache();
    }

    /**
     * 设置当前语言
     *
     * @param string $locale 语言标识
     */
    public static function setLocale(string $locale): void
    {
        self::$locale = strtolower($locale);
        // 清除已加载的语言包，以便重新加载
        self::$loaded = [];
    }

    /**
     * 获取当前语言
     *
     * @return string
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * 获取默认语言
     *
     * @return string
     */
    public static function getDefaultLocale(): string
    {
        return self::$defaultLocale;
    }

    /**
     * 检查是否已初始化
     *
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * 设置语言文件路径
     *
     * @param string $path 路径
     */
    public static function setLangPath(string $path): void
    {
        self::$langPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * 获取语言文件路径
     *
     * @return string
     */
    public static function getLangPath(): string
    {
        return self::$langPath;
    }

    /**
     * 翻译文本
     *
     * @param string $key 翻译键名，支持点号分隔（如：validation.required）
     * @param array $replace 替换变量
     * @param string|null $locale 指定语言，null则使用当前语言
     * @return string
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?: self::$locale;
        $locale = strtolower($locale);

        // 解析键名，获取文件和键
        $parts = explode('.', $key);
        $file = array_shift($parts);
        $itemKey = implode('.', $parts);

        // 加载语言文件
        $translations = self::load($file, $locale);

        // 获取翻译值
        $value = self::getNestedValue($translations, $itemKey);

        // 如果未找到且启用了回退，尝试默认语言
        if ($value === null && self::$fallback && $locale !== self::$defaultLocale) {
            $translations = self::load($file, self::$defaultLocale);
            $value = self::getNestedValue($translations, $itemKey);
        }

        // 如果仍未找到，返回键名
        if ($value === null) {
            $value = $key;
        }

        // 替换变量
        return self::replaceVariables($value, $replace);
    }

    /**
     * 翻译文本（trans的别名）
     *
     * @param string $key 翻译键名
     * @param array $replace 替换变量
     * @param string|null $locale 指定语言
     * @return string
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        return self::trans($key, $replace, $locale);
    }

    /**
     * 根据数量进行复数翻译
     *
     * @param string $key 翻译键名
     * @param int $count 数量
     * @param array $replace 替换变量
     * @param string|null $locale 指定语言
     * @return string
     */
    public static function transChoice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?: self::$locale;
        $replace['count'] = $count;

        $translation = self::trans($key, $replace, $locale);

        // 检查是否有复数形式（使用管道符分隔）
        if (strpos($translation, '|') === false) {
            return $translation;
        }

        $parts = explode('|', $translation);

        // 根据数量选择正确的形式
        return self::choosePluralForm($parts, $count, $locale);
    }

    /**
     * 加载语言文件
     *
     * @param string $file 文件名
     * @param string $locale 语言
     * @return array
     */
    protected static function load(string $file, string $locale): array
    {
        $cacheKey = $locale . '.' . $file;

        if (isset(self::$loaded[$cacheKey])) {
            return self::$loaded[$cacheKey];
        }

        // 构建正确的路径
        $path = self::$langPath . $locale . '/' . $file . '.php';

        if (file_exists($path)) {
            $content = require $path;
            self::$loaded[$cacheKey] = is_array($content) ? $content : [];
        } else {
            // 尝试使用 DIRECTORY_SEPARATOR
            $path = self::$langPath . $locale . DIRECTORY_SEPARATOR . $file . '.php';
            if (file_exists($path)) {
                $content = require $path;
                self::$loaded[$cacheKey] = is_array($content) ? $content : [];
            } else {
                self::$loaded[$cacheKey] = [];
            }
        }

        return self::$loaded[$cacheKey];
    }

    /**
     * 获取嵌套数组值
     *
     * @param array $array 数组
     * @param string $key 键名（支持点号分隔）
     * @return mixed
     */
    protected static function getNestedValue(array $array, string $key)
    {
        if (empty($key)) {
            return $array;
        }

        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 替换变量
     *
     * @param string $message 消息模板
     * @param array $replace 替换变量
     * @return string
     */
    protected static function replaceVariables(string $message, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $message = str_replace(['{' . $key . '}', ':' . $key], $value, $message);
        }
        return $message;
    }

    /**
     * 选择复数形式
     *
     * @param array $parts 复数形式数组
     * @param int $count 数量
     * @param string $locale 语言
     * @return string
     */
    protected static function choosePluralForm(array $parts, int $count, string $locale): string
    {
        // 中文和日语等语言通常没有复数变化
        if (in_array($locale, ['zh-cn', 'zh-tw', 'ja', 'ko'])) {
            return $parts[0] ?? '';
        }

        // 英语等语言的复数规则：1为单数，其他为复数
        if (count($parts) >= 2) {
            return $count === 1 ? $parts[0] : $parts[1];
        }

        return $parts[0] ?? '';
    }

    /**
     * 获取所有支持的语言列表
     *
     * @return array
     */
    public static function getSupportedLocales(): array
    {
        // 直接返回支持的语言列表，避免目录读取问题
        return ['zh-cn', 'en'];
    }

    /**
     * 检查语言是否支持
     *
     * @param string $locale 语言标识
     * @return bool
     */
    public static function hasLocale(string $locale): bool
    {
        $locale = strtolower($locale);
        $supported = self::getSupportedLocales();
        return in_array($locale, $supported);
    }

    /**
     * 从请求中检测语言
     * 优先级：URL参数 > Cookie > Header > 默认
     *
     * @param array $params 请求参数
     * @param array $headers 请求头
     * @return string
     */
    public static function detectLocale(array $params = [], array $headers = []): string
    {
        // 1. 检查URL参数
        if (!empty($params['lang'])) {
            $locale = strtolower($params['lang']);
            if (self::hasLocale($locale)) {
                return $locale;
            }
        }

        // 2. 检查Cookie
        if (!empty($_COOKIE['lang'])) {
            $locale = strtolower($_COOKIE['lang']);
            if (self::hasLocale($locale)) {
                return $locale;
            }
        }

        // 3. 检查Accept-Language头
        if (!empty($headers['Accept-Language'])) {
            $locale = self::parseAcceptLanguage($headers['Accept-Language']);
            if ($locale && self::hasLocale($locale)) {
                return $locale;
            }
        }

        return self::$defaultLocale;
    }

    /**
     * 解析Accept-Language头
     *
     * @param string $header Accept-Language头值
     * @return string|null
     */
    protected static function parseAcceptLanguage(string $header): ?string
    {
        $locales = [];
        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, ';') !== false) {
                list($locale, $q) = explode(';', $part, 2);
                $q = (float) str_replace('q=', '', $q);
            } else {
                $locale = $part;
                $q = 1.0;
            }
            $locales[trim($locale)] = $q;
        }

        // 按优先级排序
        arsort($locales);

        foreach (array_keys($locales) as $locale) {
            $locale = strtolower($locale);
            // 处理类似 zh-CN, en-US 的格式
            $locale = str_replace('_', '-', $locale);
            if (self::hasLocale($locale)) {
                return $locale;
            }
            // 尝试匹配主语言（如 zh-CN 匹配 zh-cn 或 zh）
            $primary = explode('-', $locale)[0];
            if (self::hasLocale($primary)) {
                return $primary;
            }
        }

        return null;
    }

    /**
     * 清除已加载的语言缓存
     */
    public static function clearCache(): void
    {
        self::$loaded = [];
        self::$langList = [];
    }
}
