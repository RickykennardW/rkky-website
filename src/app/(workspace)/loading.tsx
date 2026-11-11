export default function Loading() {
  return <div role="status" aria-label="Loading CampusSpace" className="grid gap-5 motion-safe:animate-pulse">
    <div className="h-9 w-48 rounded-lg bg-raised" /><div className="h-56 rounded-xl bg-panel" />
    <div className="grid gap-4 md:grid-cols-3">{[0, 1, 2].map((key) => <div key={key} className="h-28 rounded-xl bg-panel" />)}</div>
  </div>;
}
