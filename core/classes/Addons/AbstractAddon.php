<?php

namespace AmminaISP\Core\Addons;


set_time_limit(60);

abstract class AbstractAddon
{
	public string $xml = '';
	public array $dataAppend = [];
	public string $stringAppend = '';

	public function __construct()
	{
		$this->xml = '';
		while (!feof(STDIN)) {
			$this->xml .= fread(STDIN, 10000);
		}
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
		if (strlen($this->stringAppend) > 0) {
			$this->xml = str_replace('</doc>', $this->stringAppend . '</doc>', $this->xml);
		}
		if ($return) {
			return $this->xml;
		}
		echo $this->xml;
		return null;
	}

	abstract public function openForm(): void;

	abstract public function saveForm(): void;
}