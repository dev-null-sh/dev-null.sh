export type ApiDocsSection = {
    slug: string;
    title: string;
    description: string;
    kind: 'guide' | 'reference';
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

export type ApiDocsField = {
    name: string;
    type: string;
    required: boolean;
    description: string | null;
    enum: (string | number)[] | null;
    children: ApiDocsField[];
};

export type ApiDocsParameter = ApiDocsField & { in: string };

export type ApiDocsResponse = {
    status: string;
    description: string;
    fields: ApiDocsField[];
};

export type ApiDocsEndpoint = {
    id: string;
    method: string;
    path: string;
    summary: string;
    description: string | null;
    signature: 'required' | 'optional';
    parameters: ApiDocsParameter[];
    body: { mediaType: string; fields: ApiDocsField[] } | null;
    responses: ApiDocsResponse[];
    examples: ApiDocsExample[];
};

export type ApiDocsExample = {
    label: string;
    language: string;
    code: string;
    html: string;
};

export type ApiDocsIndexEntry = {
    id: string;
    method: string;
    path: string;
    summary: string;
    section: string;
};

export type ApiDocsProps = {
    sections: ApiDocsSection[];
    current: string;
    endpoints: ApiDocsEndpoint[];
    index: ApiDocsIndexEntry[];
    signing: ApiDocsExample[];
    rateLimits: ApiRateLimit[];
    snippets: Record<string, ApiDocsSnippet>;
    errorCodes: ApiErrorCodeStatus[];
};
