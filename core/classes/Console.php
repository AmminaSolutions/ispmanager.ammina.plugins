<?php
/**
 * Оригинальные исходники подсветки консольного вывода:
 * (c) 2017 wujunze https://github.com/wujunze/php-cli-color
 *
 * Доработано для PHP 8.2 и только статичное использование класса
 */

namespace AmminaISP\Core;

class Console
{
	private static array $foregroundColors = [
		'black' => '0;30',
		'dark_gray' => '1;30',
		'blue' => '0;34',
		'light_blue' => '1;34',
		'green' => '0;32',
		'light_green' => '1;32',
		'cyan' => '0;36',
		'light_cyan' => '1;36',
		'red' => '0;31',
		'light_red' => '1;31',
		'purple' => '0;35',
		'light_purple' => '1;35',
		'brown' => '0;33',
		'yellow' => '1;33',
		'light_gray' => '0;37',
		'white' => '1;37',
	];
	private static array $backgroundColors = [
		'black' => '40',
		'red' => '41',
		'green' => '42',
		'yellow' => '43',
		'blue' => '44',
		'magenta' => '45',
		'cyan' => '46',
		'light_gray' => '47',
	];


	public static function showColoredString(string $string, ?string $foreground_color = null, ?string $background_color = null, bool $new_line = false): void
	{
		echo self::getColoredString($string, $foreground_color, $background_color, $new_line);
	}

	/**
	 * Возвращает цветную строку
	 *
	 * @param string $string
	 * @param string|null $foreground_color
	 * @param string|null $background_color
	 * @param bool $new_line
	 * @return string
	 */
	public static function getColoredString(string $string, ?string $foreground_color = null, ?string $background_color = null, bool $new_line = false): string
	{
		$colored_string = '';

		// Проверяем, найден ли данный цвет текста
		if (isset(self::$foregroundColors[$foreground_color])) {
			$colored_string .= "\033[" . self::$foregroundColors[$foreground_color] . 'm';
		}
		// Проверяем, найден ли цвет фона
		if (isset(self::$backgroundColors[$background_color])) {
			$colored_string .= "\033[" . self::$backgroundColors[$background_color] . 'm';
		}

		// Добавляем строку и завершаем раскраску
		$colored_string .= $string . "\033[0m";

		return $new_line ? $colored_string . PHP_EOL : $colored_string;
	}

	/**
	 * Получить цветной текст
	 *
	 * @param string $string
	 * @param string|null $foregroundColor Цвет текста black|dark_gray|blue|light_blue|green|light_green|cyan|light_cyan|red|light_red|purple|brown|yellow|light_gray|white
	 * @param string|null $backgroundColor Цвет фона black|red|green|yellow|blue|magenta|cyan|light_gray
	 *
	 * @return string
	 */
	public static function initColoredString(string $string, string $foregroundColor = null, string $backgroundColor = null): string
	{
		$coloredString = '';

		if (isset(static::$foregroundColors[$foregroundColor])) {
			$coloredString .= "\033[" . static::$foregroundColors[$foregroundColor] . 'm';
		}
		if (isset(static::$backgroundColors[$backgroundColor])) {
			$coloredString .= "\033[" . static::$backgroundColors[$backgroundColor] . 'm';
		}

		$coloredString .= $string . "\033[0m";

		return $coloredString;
	}

	/**
	 * Вывод подсказки
	 *
	 * @param string $msg
	 */
	public static function notice(string $msg): void
	{
		fwrite(STDOUT, self::initColoredString($msg, 'light_gray') . PHP_EOL);
	}

	/**
	 * Вывод ошибки
	 *
	 * @param string $msg
	 */
	public static function error(string $msg): void
	{
		fwrite(STDERR, self::initColoredString($msg, 'red') . PHP_EOL);
	}

	/**
	 * Вывод предупреждения
	 *
	 * @param string $msg
	 */
	public static function warn(string $msg): void
	{
		fwrite(STDOUT, self::initColoredString($msg, 'yellow') . PHP_EOL);
	}

	/**
	 * Вывод информации об успехе
	 *
	 * @param string $msg
	 */
	public static function success(string $msg): void
	{
		fwrite(STDOUT, self::initColoredString($msg, 'green') . PHP_EOL);
	}
}