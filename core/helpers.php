<?php

namespace AmminaISP\Core;

function checkDirPath($path, $permission = 0700): bool
{
	return Utils::checkDirPath($path, $permission);
}

function addJob($strCommand, $arOptions): void
{
	Utils::addJob($strCommand, $arOptions);
}

function boolToFlag(bool $value): string
{
	return Utils::boolToFlag($value);
}

function isOn(string $value): bool
{
	return Utils::isOn($value);
}

function checkOnOff(string $value): string
{
	return Utils::checkOnOff($value);
}