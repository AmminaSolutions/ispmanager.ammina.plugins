<?php

namespace AmminaISP\Core\Addons;


use function AmminaISP\Core\boolToFlag;
use function AmminaISP\Core\checkDirPath;
use function AmminaISP\Core\isOn;

set_time_limit(60);

abstract class AbstractAddon
{
	public string $xml = '';
	public array $dataAppend = [];
	public array $stringsAppend = [];
	public string $settingsDir = '/usr/local/mgr5/etc/amminaisp/';
	public ?string $settingsFile = null;
	public ?array $iniData = null;

	public function __construct()
	{
		$this->xml = '';
		while (!feof(STDIN)) {
			$this->xml .= fread(STDIN, 10000);
		}
	}

	/**
	 * Возвращает имя файла настроек плагина
	 * @return string|null
	 */
	protected function settingsFileName(): ?string
	{
		if (!is_null($this->settingsFile)) {
			checkDirPath("{$this->settingsDir}{$this->settingsFile}");
			return "{$this->settingsDir}{$this->settingsFile}";
		}
		return null;
	}

	/**
	 * Выполнение функции плагина
	 * @return void
	 */
	public function run(): void
	{
		if (isset($_SERVER['PARAM_sok']) && $_SERVER['PARAM_sok'] == "ok") {
			$this->saveForm();
		} else {
			$this->openForm();
		}
		$this->renderXml();
	}

	/**
	 * Формирование XML ответа
	 *
	 * @param bool $return
	 * @return string|null
	 */
	protected function renderXml(bool $return = false): ?string
	{
		if (!empty($this->dataAppend)) {
			$content = [];
			foreach ($this->dataAppend as $item => $value) {
				$value = htmlspecialchars($value);
				$content[] = "<{$item}>{$value}</{$item}>";
			}
			$this->xml = str_replace('</doc>', implode('', $content) . '</doc>', $this->xml);
		}
		if (!empty($this->stringsAppend)) {
			$this->xml = str_replace('</doc>', implode('', $this->stringsAppend) . '</doc>', $this->xml);
		}

		if ($return) {
			return $this->xml;
		}
		echo $this->xml;
		return null;
	}

	/**
	 * Проверка и загрузка ini файла настроек плагина
	 * @param bool $reloadFromFile
	 * @return void
	 */
	protected function checkLoadIni(bool $reloadFromFile = false): void
	{
		if ($reloadFromFile) {
			$this->iniData = null;
		}
		$fileName = $this->settingsFileName();
		if (!is_null($this->iniData)) {
			return;
		}
		if (is_null($fileName) || !file_exists($fileName)) {
			$this->iniData = [];
		} else {
			$this->iniData = parse_ini_file($fileName, false);
		}
	}

	/**
	 * Получить параметр из настроек
	 * @param string|null $name
	 * @param mixed|null $default
	 * @return mixed
	 */
	protected function fromIni(?string $name = null, mixed $default = null): mixed
	{
		$this->checkLoadIni();
		if (is_null($name)) {
			return $this->iniData;
		}
		return $this->iniData[$name] ?? $default;
	}

	/**
	 * Получить флаг из настроек
	 * @param string|null $name
	 * @param mixed|null $default
	 * @return string|null
	 */
	protected function fromIniFlag(?string $name = null, mixed $default = null): ?string
	{
		return boolToFlag($this->fromIni($name, $default));
	}

	/**
	 * Установить параметр настроек
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 */
	protected function setIni(string $name, mixed $value): static
	{
		$this->checkLoadIni();
		$this->iniData[$name] = $value;
		return $this;
	}

	/**
	 * Установить параметр настроек из параметра формы в $_SERVER['PARAM_...']
	 * @param string $name
	 * @param string|null $serverParamName
	 * @return $this
	 */
	protected function setIniFromParam(string $name, ?string $serverParamName = null): static
	{
		if (is_null($serverParamName)) {
			$serverParamName = $name;
		}
		return $this->setIni($name, $_SERVER["PARAM_{$serverParamName}"] ?? null);
	}

	/**
	 * Установить флаг настроек
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 */
	protected function setIniFlag(string $name, mixed $value): static
	{
		return $this->setIni($name, (isOn($value ?? 'off') ? 1 : 0));
	}

	/**
	 * Установить флаг настроек из параметра формы в $_SERVER['PARAM_...']
	 *
	 * @param string $name
	 * @param string|null $serverParamName
	 * @return $this
	 */
	protected function setIniFlagFromParam(string $name, ?string $serverParamName = null): static
	{
		if (is_null($serverParamName)) {
			$serverParamName = $name;
		}
		return $this->setIniFlag($name, $_SERVER["PARAM_{$serverParamName}"] ?? null);
	}

	/**
	 * Сохранить файл настроек
	 * @return $this
	 */
	protected function saveIni(): static
	{
		$fileName = $this->settingsFileName();
		if (!is_null($fileName)) {
			$content = [];
			foreach ($this->iniData as $name => $value) {
				$content[] = "{$name}={$value}";
			}
			file_put_contents($fileName, implode("\n", $content));
		}
		return $this;
	}

	protected function getFromParam(string $serverParamName): string
	{
		return trim($_SERVER["PARAM_{$serverParamName}"] ?? '');
	}

	protected function getFlagFromParam(string $serverParamName): string
	{
		return boolToFlag(isOn($_SERVER["PARAM_{$serverParamName}"] ?? ''));
	}

	abstract public function openForm(): void;

	abstract public function saveForm(): void;
}