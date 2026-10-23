export interface Category
{
    id:number;
    name:string;
    description: string | null;
    created_at : string;
    updated_at: string;
}

export interface PaginatedResponse<T>
{
    data:T[];
    current_page: number;
    last_page:number;
    per_page:number;
    total:number;
}