<?php

namespace AmminaISP\Core\Addons;

use function AmminaISP\Core\boolToFlag;

abstract class AbstractSiteEdit extends AbstractAddon
{
	public function openForm(): void
	{
		$this->dataAppend['use_php'] = boolToFlag($_SERVER['PARAM_site_handler'] == 'handler_php');
		if ($_SERVER['PARAM_platform'] !== 'default') {
			$this->xml = str_replace('<site_phpcomposer>on</site_phpcomposer>', '<site_phpcomposer>off</site_phpcomposer>', $this->xml);
		}

		$this->stringAppend = '<slist name="platform"><msg>default</msg><msg>bitrix</msg><msg>laravel</msg></slist>';
	}

	public function saveForm(): void
	{
		//$this->dataAppend['plugin_note'] = 'tesfghdfghfdghfdght';
		// TODO: Implement saveForm() method.
	}

	protected function renderXml(bool $return = false): ?string
	{
		$xml = parent::renderXml(true);
		[$xml, $platformField] = $this->getFormXmlField($xml, 'platform', '');
		[$xml] = $this->getFormXmlField($xml, 'site_handler', null, $platformField);
		file_put_contents('/opt/1.xml', $xml);
		echo $xml;
		return null;
	}

	protected function getFormXmlField(string $xml, string $name, ?string $replace = null, ?string $after = null): array
	{
		$resultXml = $xml;
		$resultField = null;
		$findContent = "<field name=\"{$name}\"";
		$startPos = strpos($xml, $findContent);
		if ($startPos !== false) {
			$ar = explode($findContent, $xml, 2);
			$ar2 = explode('</field>', $ar[1], 2);
			$resultField = $findContent . $ar2[0] . '</field>';
			$resultXml = $ar[0] . (is_null($replace) ? $resultField : $replace) . (is_null($after) ? '' : $after) . $ar2[1];
		}
		return [$resultXml, $resultField];
	}
}