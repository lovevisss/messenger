export type ApiDocExample = {
    title: string;
    description: string;
    body: string;
    curl: string;
    powershell: string;
};

export type ApiDocumentation = {
    title: string;
    intro: string;
    apiUrl: string;
    queryUrl: string;
    examples: ApiDocExample[];
    fields: [string, string, string][];
    extraFields?: [string, string, string][];
    response: string;
    validationResponse?: string;
    messageTemplate?: string;
    upstream?: [string, string][];
    notes: string[];
    errors: [string, string][];
    operations?: [string, string, string, string][];
};
