<?php

namespace AmminaISP\Core;


function checkDirPath($path, $permission = 0760): bool
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

function joinPaths(string $basePath, string ...$paths): string
{
	return Utils::joinPaths($basePath, ...$paths);
}

function removePathRoot(string $path, ?string $root = null): string
{
	return Utils::removePathRoot($path, $root);
}

function findFile(string $path): ?string
{
	return Utils::findFile($path);
}