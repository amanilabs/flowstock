# FlowStock Admin Dashboard

A React + TypeScript admin dashboard for the FlowStock API — products,
categories, warehouses, stock, customers, orders (full lifecycle), and
audit logs (Admin only), with role-aware UI.

## Stack

React 19 · TypeScript · Vite · Tailwind CSS v4 · shadcn/ui · React Router
· TanStack Query · React Hook Form + Zod

## Getting started

Requires the FlowStock API running (see the repo root README) and Node 20+.

```bash
cp .env.example .env
npm install
npm run dev
```

Runs at `http://localhost:5173`, talking to the API at `VITE_API_URL`
(defaults to `http://localhost:8081/api/v1`).

Log in with the demo seeder's credentials (`admin@acme.test` /
`manager@acme.test` / `staff@acme.test`, password `password`) — see the
root README's "Getting started" section to seed them.

## Architecture

- **Auth**: bearer token in `localStorage` (`src/contexts/AuthContext.tsx`),
  attached to every request by `src/lib/api.ts`. A 401 response clears the
  token and redirects to `/login`.
- **Data fetching**: one hook file per API resource under
  `src/hooks/queries/`, each wrapping TanStack Query for caching and
  cache invalidation on mutation.
- **RBAC-aware UI**: `useAuth().hasRole(...)` gates nav items (Audit Logs
  is Admin-only) and order lifecycle action buttons, mirroring the
  backend's actual permission matrix — the API is still the real
  enforcement point, this just avoids showing actions a role can't use.
- **shadcn/ui components** live under `src/components/ui/` — generated,
  not hand-written; edit them like any other component, don't expect
  `npx shadcn add` to preserve local changes.

## Scripts

```bash
npm run dev      # dev server
npm run build    # type-check + production build
npm run preview  # preview the production build locally
```
