export interface GenerationField { key: string; type: string; required: boolean }
export interface GenerationContext { reference: string; sections: { title: string; rows: { key: string; value: string }[] }[] }
