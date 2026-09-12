interface PlaceholderPageProps {
  title: string
  description: string
  icon?: string
}

export default function PlaceholderPage({ title, description, icon = 'construction' }: PlaceholderPageProps) {
  const icons: Record<string, string> = {
    construction: 'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
    document: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z',
    search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.35-4.35',
  }

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold text-ink mb-6">{title}</h1>
      <div className="bg-surface rounded-lg shadow-sm border border-line p-12 flex flex-col items-center justify-center text-center">
        <div className="w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center mb-4">
          <svg className="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d={icons[icon] || icons.construction} />
          </svg>
        </div>
        <h2 className="text-lg font-semibold text-ink mb-2">Coming Soon</h2>
        <p className="text-ink-muted max-w-md">{description}</p>
        <div className="mt-6 px-4 py-2 bg-blue-50 text-blue-700 rounded-md text-sm font-medium">
          This feature is being migrated from the backend admin panel
        </div>
      </div>
    </div>
  )
}
