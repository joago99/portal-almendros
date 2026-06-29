-- Portal Construcciones Los Almendros
-- Supabase / Postgrest + Auth (sin Google)

-- Usuario admin inicial (cambiar luego)
-- email: admin@losalmendros.cl
-- pass : admin123456 (temporal)

-- 1.1 App users (máscara sobre auth)
create table public.app_users (
  id uuid references auth.users(id) on delete cascade primary key,
  email text unique not null,
  role text not null default 'client' check (role in ('admin','staff','client')),
  name text,
  profile text, -- 'client' | 'client'
  client_id uuid,
  force_password_change boolean default false,
  last_login timestamptz,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

-- 1.2 Clientes
create table public.clients (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  email text unique,
  phone text,
  rut text,
  user_id uuid null, -- dueño del portal (app_users)
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

-- 1.3 Proyectos
create table public.projects (
  id uuid primary key default gen_random_uuid(),
  client_id uuid not null references public.clients(id) on delete cascade,
  name text not null,
  description text,
  status text not null default 'en_progreso' check (status in ('en_progreso','pausado','finalizado')),
  start_date date,
  end_date_estimated date,
  end_date_real date,
  budget_clp numeric,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

-- 1.4 Eventos de avance
create table public.progress_events (
  id uuid primary key default gen_random_uuid(),
  project_id uuid not null references public.projects(id) on delete cascade,
  title text not null,
  description text,
  event_date date not null,
  is_paid_event boolean default false,
  created_by uuid not null references public.app_users(id) on delete restrict,
  created_at timestamptz default now()
);

create table public.progress_photos (
  id uuid primary key default gen_random_uuid(),
  event_id uuid not null references public.progress_events(id) on delete cascade,
  url text not null,
  caption text,
  uploaded_by uuid not null references public.app_users(id) on delete restrict,
  uploaded_at timestamptz default now()
);

-- 1.5 Documentos
create table public.documents (
  id uuid primary key default gen_random_uuid(),
  project_id uuid not null references public.projects(id) on delete cascade,
  type text not null check (type in ('presupuesto','avance','plano','legal','otro')),
  title text not null,
  file_path text not null,
  uploaded_by uuid not null references public.app_users(id) on delete restrict,
  uploaded_at timestamptz default now()
);

-- 1.6 Pagos
create table public.payments (
  id uuid primary key default gen_random_uuid(),
  project_id uuid not null references public.projects(id) on delete cascade,
  concept text not null,
  amount_clp numeric not null,
  due_date date not null,
  status text not null default 'pendiente' check (status in ('pendiente','pagado','atrasado')),
  paid_at date null,
  receipt_path text null,
  created_by uuid not null references public.app_users(id) on delete restrict,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

-- 1.7 Auditoría
create table public.audit_logs (
  id uuid primary key default gen_random_uuid(),
  actor_id uuid not null references public.app_users(id) on delete set null,
  entity text not null,
  entity_id uuid not null,
  action text not null,
  snapshot jsonb,
  created_at timestamptz default now()
);

-- 2. Triggers (updated_at)
create or replace function public.set_updated_at()
returns trigger as $$
begin
  new.updated_at = now();
  return new;
end;
$$ language plpgsql;

drop trigger if exists set_updated_at on public.projects;
create trigger set_updated_at before update on public.projects for each row execute function public.set_updated_at();

drop trigger if exists set_updated_at on public.payments;
create trigger set_updated_at before update on public.payments for each row execute function public.set_updated_at();

-- 3. Gates / helper para saber clave por cliente
create or replace function public.my_client_id()
returns uuid as $$
begin
  declare uid uuid := auth.uid();
  return (select client_id from public.clients where user_id = uid limit 1);
end;
$$ language plpgsql stable;

-- 4. Seed Admin (ajustar luego)
-- insert into auth.users (id, email, email_confirmed_at, encrypted_password, aud, role, raw_app_meta_data, raw_user_meta_data)
-- values (gen_random_uuid(), 'admin@losalmendros.cl', now(), crypt('admin123456', gen_salt('bf')), 'authenticated', 'authenticated', '{}', '{}');

-- Siempre desde dashboard > users porque desde aquí puede ser necesario un user_id exacto
