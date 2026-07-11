export interface Entry {
    id: number;
    url: string;
    host: string;
    title: string | null;
    tagline: string;
    author_name: string;
    x_handle: string | null;
    og_image_url: string | null;
    screenshot_url: string | null;
    votes_count: number;
    has_pending_shot: boolean;
}
