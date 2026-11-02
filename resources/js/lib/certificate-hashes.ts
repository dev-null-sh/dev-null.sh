// Certificate fingerprints listed in https://blog.talosintelligence.com/compromised-intermediate-certificates/
const compromisedHashes = [
    'E7568FE6E12331F4FD7DE61DA4C7559D0F8EB192A218D4741A3BA75184333838',
    '98B896625C0C230ADEEC3EB2CF6AAD5001ECF9156A68644E82374CE5833BC25B',
    '42C4ACCD12C4F59597925E1A08A7A90102608543809308D50EC427E118BA1493',
    '432EC25F0D28DE9006D97BF752F17BBB2CD41F669933FCB072D9A1148D0261F3',
    'CB6E15165AC802E9B3F993F16C25D41F4E81C21EBDE71AE1B012AC00BF9C7C3F',
    '0164D7CEAE5BB909C272AF151FF44B1177AE9B47D9424B94F1D9558C30934D19',
    '9E39DE2881273ACC225A149C72BA3E0F51424963669AB633EE1DC7DF5DB067B4',
    '4B2460A466F95FD40113BCDCCA2CEB29B3B093921DE45474DA7DF48874C630B1',
];

export const certificateHashPattern = new RegExp(
    `(?<![a-f0-9])(${compromisedHashes.join('|')})(?![a-f0-9])`,
    'gi',
);

/** Decorates only text in already escaped, server-highlighted code, never HTML attributes. */
export function withCertificateHashes(html: string): string {
    return html
        .split(/(<[^>]*>)/g)
        .map((part, index) =>
            index % 2 === 0
                ? part.replace(
                      certificateHashPattern,
                      '<span class="certificate-hash"><span>$1</span></span>',
                  )
                : part,
        )
        .join('');
}
