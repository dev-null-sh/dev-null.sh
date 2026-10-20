<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;

final readonly class ContributorsQuery
{
    /**
     * The contributors, each with their /dev/null username, or null when they aren't on it. They are found by their
     * GitHub account, since a username can change on either side.
     *
     * @return list<array{login: string, commits: int, username: string|null}>
     */
    public function get(): array
    {
        $contributors = $this->contributors();

        $usernames = User::query()
            ->whereIn('github_id', array_column($contributors, 'github_id'))
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->github_id => $user->username]);

        return array_map(fn (array $contributor): array => [
            'login' => $contributor['login'],
            'commits' => $contributor['commits'],
            'username' => $usernames->get($contributor['github_id']),
        ], $contributors);
    }

    /**
     * Everyone with a commit on production, most commits first, by GitHub login and account. Bots and agents are left
     * out.
     *
     * @return list<array{login: string, github_id: string, commits: int}>
     */
    private function contributors(): array
    {
        return [
            ['login' => 'xHeaven', 'github_id' => '14284867', 'commits' => 199],
            ['login' => 'nunomaduro', 'github_id' => '5457236', 'commits' => 176],
            ['login' => 'mnapoli', 'github_id' => '720328', 'commits' => 61],
            ['login' => 'brendt', 'github_id' => '6905297', 'commits' => 42],
            ['login' => 'simonhamp', 'github_id' => '31628', 'commits' => 18],
            ['login' => 'paulinevos', 'github_id' => '5345273', 'commits' => 16],
            ['login' => 'jubianchi', 'github_id' => '327237', 'commits' => 16],
            ['login' => 'MrPunyapal', 'github_id' => '53343069', 'commits' => 15],
            ['login' => 'sadiqk2', 'github_id' => '88760696', 'commits' => 8],
            ['login' => 'camilleislasse', 'github_id' => '34024380', 'commits' => 6],
            ['login' => 'hbenabdallah', 'github_id' => '63171491', 'commits' => 5],
            ['login' => 'vtlxx', 'github_id' => '112337985', 'commits' => 5],
            ['login' => 'robelsust', 'github_id' => '12664097', 'commits' => 4],
            ['login' => 'HelgeSverre', 'github_id' => '1089652', 'commits' => 4],
            ['login' => 'SanderMuller', 'github_id' => '9074391', 'commits' => 3],
            ['login' => 'MaximeGoncalves', 'github_id' => '35679360', 'commits' => 3],
            ['login' => 'mathiasonea', 'github_id' => '9391581', 'commits' => 3],
            ['login' => 'fernandofreamunde', 'github_id' => '3598030', 'commits' => 3],
            ['login' => 'mon4ssi', 'github_id' => '4429864', 'commits' => 2],
            ['login' => 'hbenjacquet', 'github_id' => '244774096', 'commits' => 2],
            ['login' => 'Akiyo974', 'github_id' => '123602948', 'commits' => 2],
            ['login' => 'Chemaclass', 'github_id' => '5256287', 'commits' => 2],
            ['login' => 'eznix86', 'github_id' => '26553194', 'commits' => 2],
            ['login' => 'BENNIEV-ZA', 'github_id' => '33395137', 'commits' => 2],
            ['login' => 'ahosker', 'github_id' => '346166', 'commits' => 2],
            ['login' => 'eramitgupta', 'github_id' => '72160684', 'commits' => 1],
            ['login' => 'utsavsomaiya', 'github_id' => '96036522', 'commits' => 1],
            ['login' => 'tooinfinity', 'github_id' => '51855906', 'commits' => 1],
            ['login' => 'murrant', 'github_id' => '39462', 'commits' => 1],
            ['login' => 'RobinMalfait', 'github_id' => '1834413', 'commits' => 1],
            ['login' => 'prateekbhujel', 'github_id' => '13213140', 'commits' => 1],
            ['login' => 'ondrejmirtes', 'github_id' => '104888', 'commits' => 1],
            ['login' => 'lazarospsa', 'github_id' => '37215838', 'commits' => 1],
            ['login' => 'kachelle', 'github_id' => '33377566', 'commits' => 1],
            ['login' => 'kadirtikil', 'github_id' => '92791650', 'commits' => 1],
            ['login' => 'jjaybrown', 'github_id' => '477626', 'commits' => 1],
            ['login' => 'GTS-MEGHNI', 'github_id' => '47923380', 'commits' => 1],
            ['login' => 'faissaloux', 'github_id' => '60013703', 'commits' => 1],
            ['login' => 'fabpl', 'github_id' => '6574025', 'commits' => 1],
            ['login' => 'danielh-official', 'github_id' => '49914607', 'commits' => 1],
            ['login' => 'chrisgocode', 'github_id' => '96142828', 'commits' => 1],
            ['login' => 'AmanuelLegese', 'github_id' => '24702768', 'commits' => 1],
            ['login' => 'alaminfirdows', 'github_id' => '30468274', 'commits' => 1],
            ['login' => 'aaqidmasoodi', 'github_id' => '60925897', 'commits' => 1],
        ];
    }
}
