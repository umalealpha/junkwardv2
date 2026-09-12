import ReactMarkdown, { type Components } from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { Link } from 'react-router-dom'
import { slugify, nodeText } from '../../help/slug'

// Tailwind-styled renderers for guide markdown. We map elements explicitly
// rather than pulling in @tailwindcss/typography so the styling matches the
// rest of the portal (brand navy headings, compact spacing).
const components: Components = {
  h1: ({ children }) => <h1 className="text-2xl font-bold text-ink mt-8 mb-3 first:mt-0">{children}</h1>,
  h2: ({ children }) => (
    <h2
      id={slugify(nodeText(children))}
      className="text-lg font-bold text-brand-navy dark:text-ink mt-7 mb-2 pb-1 border-b border-line scroll-mt-4"
    >
      {children}
    </h2>
  ),
  h3: ({ children }) => <h3 className="text-base font-semibold text-ink mt-5 mb-2">{children}</h3>,
  p: ({ children }) => <p className="mb-3 leading-relaxed">{children}</p>,
  ul: ({ children }) => <ul className="list-disc pl-5 mb-3 space-y-1">{children}</ul>,
  ol: ({ children }) => <ol className="list-decimal pl-5 mb-3 space-y-1.5">{children}</ol>,
  li: ({ children }) => <li className="leading-relaxed">{children}</li>,
  strong: ({ children }) => <strong className="font-semibold text-ink">{children}</strong>,
  em: ({ children }) => <em className="italic">{children}</em>,
  code: ({ children }) => (
    <code className="px-1.5 py-0.5 rounded bg-surface-2 text-brand-navy dark:text-ink text-[0.85em] font-mono">{children}</code>
  ),
  blockquote: ({ children }) => (
    <blockquote className="border-l-4 border-brand-orange/60 bg-brand-orange/5 pl-4 py-2 my-3 text-ink-muted rounded-r">
      {children}
    </blockquote>
  ),
  hr: () => <hr className="my-6 border-line" />,
  table: ({ children }) => (
    <div className="overflow-x-auto my-4">
      <table className="min-w-full text-sm border border-line rounded-lg overflow-hidden">{children}</table>
    </div>
  ),
  thead: ({ children }) => <thead className="bg-surface-2">{children}</thead>,
  th: ({ children }) => (
    <th className="text-left font-semibold text-ink-muted px-3 py-2 border-b border-line">{children}</th>
  ),
  td: ({ children }) => <td className="px-3 py-2 border-b border-line align-top text-ink-muted">{children}</td>,
  a: ({ href, children }) => {
    const target = href ?? '#'
    // Internal app links (incl. /help/... cross-links) use the router so we
    // don't full-reload the SPA; external links open in a new tab.
    if (target.startsWith('/')) {
      return (
        <Link to={target} className="text-brand-navy font-medium underline hover:text-brand-orange transition">
          {children}
        </Link>
      )
    }
    return (
      <a
        href={target}
        target="_blank"
        rel="noreferrer"
        className="text-brand-navy font-medium underline hover:text-brand-orange transition"
      >
        {children}
      </a>
    )
  },
}

export default function Markdown({ content }: { content: string }) {
  return (
    <div className="text-[15px] text-ink-muted">
      <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
        {content}
      </ReactMarkdown>
    </div>
  )
}
