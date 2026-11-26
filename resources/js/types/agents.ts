/** One tool an agent may be allowed, with its Hungarian name (`AgentToolService`). */
export type AgentTool = {
    name: string;
    label: string;
    description: string;
};

/**
 * A group of the tool catalogue: Claude Code's own tools, one MCP server's, or
 * the mailbox-driven mail tools, which the agent form does not offer at all.
 */
export type AgentToolGroup = {
    key: string;
    kind: 'builtin' | 'mcp' | 'mailbox';
    label: string;
    description: string;
    tools: AgentTool[];
};
