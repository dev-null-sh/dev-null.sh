export type TranslationLanguage = {
    code: string;
    name: string;
    nativeName: string;
};

export function searchTranslationLanguages(
    languages: TranslationLanguage[],
    query: string,
): TranslationLanguage[] {
    const normalized = query.trim().toLowerCase();

    return languages.filter((language) =>
        [language.code, language.name, language.nativeName].some((name) =>
            name.toLowerCase().includes(normalized),
        ),
    );
}
