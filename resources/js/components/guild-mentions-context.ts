import { createContext } from 'react';
import type { GuildMentions } from '@/types/guild';

export const GuildMentionsContext = createContext<GuildMentions>({});
