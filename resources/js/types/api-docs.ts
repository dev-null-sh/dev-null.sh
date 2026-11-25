export type ApiDocsSection = {
    slug: string;
    title: string;
    description: string;
};

export type ApiDocsSnippet = {
    language: string;
    html: string;
};

export type ApiDocsProps = {
    sections: ApiDocsSection[];
    current: string;
    snippets: Record<string, ApiDocsSnippet>;
};
