import type { ApiDocsIndexEntry, ApiDocsSection } from '@/types';

export type ApiDocsResult =
    | { kind: 'guide'; id: string; section: ApiDocsSection }
    | {
          kind: 'endpoint';
          id: string;
          section: ApiDocsSection;
          endpoint: ApiDocsIndexEntry;
      };

/**
 * The guides and endpoints whose words hold every word typed, in any order and case. What matches by its method,
 * path or title comes before what only its summary mentions, and the docs' own order breaks ties.
 */
export function searchApiDocs(
    query: string,
    sections: ApiDocsSection[],
    index: ApiDocsIndexEntry[],
): ApiDocsResult[] {
    const words = query.toLowerCase().split(/\s+/).filter(Boolean);
    const bySlug = new Map(sections.map((section) => [section.slug, section]));

    const candidates: { result: ApiDocsResult; name: string; text: string }[] =
        [
            ...sections
                .filter((section) => section.kind === 'guide')
                .map((section) => ({
                    result: {
                        kind: 'guide' as const,
                        id: section.slug,
                        section,
                    },
                    name: section.title,
                    text: section.description,
                })),
            ...index.flatMap((endpoint) => {
                const section = bySlug.get(endpoint.section);

                return section === undefined
                    ? []
                    : [
                          {
                              result: {
                                  kind: 'endpoint' as const,
                                  id: endpoint.id,
                                  section,
                                  endpoint,
                              },
                              name: `${endpoint.method} ${endpoint.path} ${section.title}`,
                              text: endpoint.summary,
                          },
                      ];
            }),
        ];

    return candidates
        .map((candidate, order) => {
            const name = candidate.name.toLowerCase();
            const text = candidate.text.toLowerCase();

            return {
                ...candidate,
                order,
                matches: words.every(
                    (word) => name.includes(word) || text.includes(word),
                ),
                score: words.filter((word) => name.includes(word)).length,
            };
        })
        .filter((candidate) => candidate.matches)
        .sort((a, b) => b.score - a.score || a.order - b.order)
        .map((candidate) => candidate.result);
}
