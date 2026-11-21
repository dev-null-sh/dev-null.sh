export type ApiDocsSection = {
    slug: string;
    title: string;
    description: string;
};

export type ApiErrorCodeStatus = {
    code: string;
    status: number;
    meaning: string;
};

export type ApiDocsSnippet = {
    language: string;
    html: string;
};

export type ApiRateLimit = {
    what: string;
    limit: number;
    per: string;
};

export type ApiDocsExample = {
    label: string;
    language: string;
    code: string;
    html: string;
};

export type ApiDocsProps = {
    sections: ApiDocsSection[];
    current: string;
    signing: ApiDocsExample[];
    rateLimits: ApiRateLimit[];
    snippets: Record<string, ApiDocsSnippet>;
    errorCodes: ApiErrorCodeStatus[];
};
