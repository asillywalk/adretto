<?php

namespace Sillynet\Adretto\Contracts;

interface Translator
{
    public function exists(): bool;

    public function getCurrentLanguage(): string;

    public function renderLanguageSwitcher(?int $postId): void;
}
