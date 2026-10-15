export type ModerationRuleAction = 'reject' | 'review' | 'approve';

export type ModerationRuleMode = 'off' | 'test' | 'live';

export type ModerationStrictness = 'strict' | 'balanced' | 'lenient';

export type RuleOutcome = 'reject' | 'review' | 'approve' | 'none';

export type EvaluationDecision = 'approved' | 'rejected' | 'review' | 'failed';

export type ModerationRule = {
    id: string;
    name: string;
    matches: string;
    does_not_match: string | null;
    action: ModerationRuleAction;
    strictness: ModerationStrictness;
    mode: ModerationRuleMode;
    rejection_message: string | null;
    position: number;
};

export type ModerationRuleTemplate = Omit<ModerationRule, 'id' | 'position'>;

export type RuleResult = {
    rule_id: string;
    name: string;
    action: ModerationRuleAction;
    mode: ModerationRuleMode;
    probability: number;
    outcome: RuleOutcome;
    act_threshold: number | null;
    review_threshold: number | null;
};

export type CommitEvaluation = {
    decision: EvaluationDecision;
    test_decision: EvaluationDecision | null;
    results: RuleResult[];
    model: string | null;
    error: string | null;
    created_at: string;
};

export type ModerationPreview = {
    decision: Exclude<EvaluationDecision, 'failed'>;
    decided_by: string | null;
    test_decision: Exclude<EvaluationDecision, 'failed'>;
    test_decided_by: string | null;
    model: string;
    results: RuleResult[];
};

export type AutoModerationStats = {
    approved: number;
    rejected: number;
    review: number;
    failed: number;
    overturned: number;
};
