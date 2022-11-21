<?php

namespace Sillynet\Adretto\Contracts;

interface Translator
{
    public function exists(): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllLanguages(): array;

    public function getPostIdForLanguage(
        int $postId,
        string $languageSlug
    ): ?int;

    public function getCurrentLanguage(): string;

    public function renderLanguageSwitcher(?int $postId): void;
}
