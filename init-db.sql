CREATE TABLE IF NOT EXISTS pages (
    id SERIAL PRIMARY KEY,
    filename TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    num TEXT,
    lang TEXT,
    topic TEXT,
    content TEXT NOT NULL,
    search_vector TSVECTOR GENERATED ALWAYS AS (
        setweight(to_tsvector('russian', coalesce(num, '')), 'A') ||
        setweight(to_tsvector('russian', coalesce(lang, '')), 'B') ||
        setweight(to_tsvector('russian', coalesce(topic, '')), 'B') ||
        setweight(to_tsvector('russian', coalesce(title, '')), 'A') ||
        setweight(to_tsvector('russian', coalesce(content, '')), 'C')
    ) STORED
);

CREATE INDEX IF NOT EXISTS idx_search_vector ON pages USING GIN (search_vector);
CREATE INDEX IF NOT EXISTS idx_filename ON pages (filename);
