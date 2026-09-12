// The test/staging deployment is served from graphite-v2-fe.alphadirect.co.bw;
// prod is a different hostname, so the banner never renders there. Same build
// artifact works for both environments — the check is at runtime.
const TEST_HOSTNAMES = ['graphite-v2-fe.alphadirect.co.bw']

export const IS_TEST_ENV = TEST_HOSTNAMES.includes(window.location.hostname)

// Banner is h-10 (40px). Layout offsets in AdminLayout/Header/Sidebar use the
// matching classes (top-10 / pt-24 / calc(100vh-2.5rem)) when IS_TEST_ENV.
export default function EnvironmentBanner() {
  if (!IS_TEST_ENV) return null

  return (
    <div
      className="fixed top-0 left-0 right-0 z-overlay h-10 bg-red-600 border-b-2 border-red-800 flex items-center justify-center gap-3 select-none"
      role="alert"
    >
      <svg className="w-5 h-5 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path
          fillRule="evenodd"
          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
          clipRule="evenodd"
        />
      </svg>
      <span className="text-white font-extrabold text-sm sm:text-base tracking-[0.2em] uppercase">
        Test Environment
      </span>
      <span className="hidden md:inline text-red-100 text-xs font-medium">
        — This is NOT production. Data entered here is for testing only.
      </span>
      <svg className="w-5 h-5 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path
          fillRule="evenodd"
          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
          clipRule="evenodd"
        />
      </svg>
    </div>
  )
}
