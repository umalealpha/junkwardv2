/**
 * SmartUploadProcessing3D — animated WebGL status element for the Smart
 * Underwriting Upload page. Live 3D "extraction" scene (policy core + orange
 * wireframe shell + inbound data particles + progress ring) so the underwriter
 * sees the upload is actively being read, not frozen. Disposes on unmount.
 */
import { useEffect, useRef } from 'react'
import * as THREE from 'three'

type Phase = 'idle' | 'uploading' | 'extracting' | 'completed' | 'failed'

export default function SmartUploadProcessing3D(
  // `stage` is the backend's own view: 'queued' = nothing has started yet.
  // Optional so existing call sites keep working unchanged.
  { phase, pct, elapsed, stage }:
  { phase: Phase; pct: number; elapsed: number; stage?: 'queued' | 'processing' | null },
) {
  const canvasRef = useRef<HTMLCanvasElement | null>(null)
  const phaseRef = useRef(phase); const pctRef = useRef(pct)
  phaseRef.current = phase; pctRef.current = pct

  useEffect(() => {
    const canvas = canvasRef.current; if (!canvas) return
    const ORANGE = 0xf4a623, STEEL = 0x6aa9ff, NAVY = 0x0d1b2a
    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true })
    const scene = new THREE.Scene(); scene.fog = new THREE.FogExp2(NAVY, 0.045)
    const cam = new THREE.PerspectiveCamera(50, 16 / 9, 0.1, 100); cam.position.set(0, 0, 8.5)
    const resize = () => {
      const w = canvas.clientWidth || 600, h = canvas.clientHeight || 260
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2)); renderer.setSize(w, h, false)
      cam.aspect = w / h; cam.updateProjectionMatrix()
    }
    scene.add(new THREE.AmbientLight(0xffffff, 0.5))
    const key = new THREE.PointLight(ORANGE, 1.4, 40); key.position.set(6, 5, 6); scene.add(key)
    const rim = new THREE.PointLight(STEEL, 1.1, 40); rim.position.set(-6, -3, 4); scene.add(rim)
    const grp = new THREE.Group(); scene.add(grp)
    const core = new THREE.Mesh(new THREE.IcosahedronGeometry(2, 1), new THREE.MeshStandardMaterial({ color: 0x14304a, metalness: 0.6, roughness: 0.35, flatShading: true })); grp.add(core)
    const shell = new THREE.Mesh(new THREE.IcosahedronGeometry(2.05, 1), new THREE.MeshBasicMaterial({ color: ORANGE, wireframe: true, transparent: true, opacity: 0.55 })); grp.add(shell)
    const ring = new THREE.Mesh(new THREE.TorusGeometry(3.1, 0.04, 12, 120), new THREE.MeshBasicMaterial({ color: ORANGE, transparent: true, opacity: 0.9 })); ring.rotation.x = Math.PI / 2.1; scene.add(ring)
    const ringBg = new THREE.Mesh(new THREE.TorusGeometry(3.1, 0.02, 8, 120), new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.12 })); ringBg.rotation.x = Math.PI / 2.1; scene.add(ringBg)
    const N = 380, pos = new Float32Array(N * 3)
    const seed: { r: number; a: number; e: number; sp: number }[] = []
    for (let i = 0; i < N; i++) {
      const r = 4 + Math.random() * 4, a = Math.random() * Math.PI * 2, e = (Math.random() - 0.5) * 4
      pos[i * 3] = Math.cos(a) * r; pos[i * 3 + 1] = e; pos[i * 3 + 2] = Math.sin(a) * r
      seed.push({ r, a, e, sp: 0.2 + Math.random() * 0.6 })
    }
    const pg = new THREE.BufferGeometry(); pg.setAttribute('position', new THREE.BufferAttribute(pos, 3))
    const pts = new THREE.Points(pg, new THREE.PointsMaterial({ color: STEEL, size: 0.07, transparent: true, opacity: 0.85 })); scene.add(pts)
    resize(); window.addEventListener('resize', resize)
    let raf = 0
    const animate = (now: number) => {
      raf = requestAnimationFrame(animate)
      const done = phaseRef.current === 'completed', failed = phaseRef.current === 'failed'
      grp.rotation.y += done ? 0.002 : 0.006; grp.rotation.x += 0.0022; shell.rotation.y -= 0.004
      core.scale.setScalar(1 + Math.sin(now * 0.002) * 0.03); ring.rotation.z += 0.01
      const p = pg.attributes.position.array as Float32Array
      for (let i = 0; i < N; i++) {
        const s = seed[i]; s.r -= s.sp * 0.016; s.a += 0.6 * 0.016
        if (s.r < 2.1) s.r = 6 + Math.random() * 2
        p[i * 3] = Math.cos(s.a) * s.r; p[i * 3 + 1] = s.e * (s.r / 8); p[i * 3 + 2] = Math.sin(s.a) * s.r
      }
      pg.attributes.position.needsUpdate = true
      const col = failed ? 0xe24b4a : done ? 0x1d9e75 : ORANGE
      ;(ring.material as THREE.MeshBasicMaterial).color.setHex(col)
      ;(shell.material as THREE.MeshBasicMaterial).color.setHex(col)
      ;(ring.material as THREE.MeshBasicMaterial).opacity = 0.35 + 0.6 * (Math.min(100, pctRef.current) / 100)
      renderer.render(scene, cam)
    }
    raf = requestAnimationFrame(animate)
    return () => {
      cancelAnimationFrame(raf); window.removeEventListener('resize', resize)
      scene.traverse((o) => {
        const mm = o as THREE.Mesh
        if (mm.geometry) mm.geometry.dispose()
        const mat = (mm as unknown as { material?: THREE.Material | THREE.Material[] }).material
        if (Array.isArray(mat)) mat.forEach((x) => x.dispose()); else if (mat) mat.dispose()
      })
      renderer.dispose()
    }
  }, [])

  const label = phase === 'uploading' ? 'Uploading your schedule…'
    : phase === 'extracting'
      ? (stage === 'queued' ? 'Starting the extraction engine…' : 'AI is reading the workbook…')
    : phase === 'completed' ? 'Extraction complete'
    : phase === 'failed' ? 'Extraction failed' : ''
  const m = Math.floor(elapsed / 60), s = (elapsed % 60).toString().padStart(2, '0')

  return (
    <div style={{ position: 'relative', width: '100%', height: 260, borderRadius: 16, overflow: 'hidden', background: '#0D1B2A' }}>
      <canvas ref={canvasRef} style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', display: 'block' }} />
      <div style={{ position: 'absolute', left: 20, bottom: 16, right: 20, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', pointerEvents: 'none' }}>
        <span style={{ color: '#fff', fontSize: 14, fontWeight: 600 }}>{label}</span>
        {(phase === 'uploading' || phase === 'extracting') && (
          <span style={{ color: '#8d99a6', fontSize: 12, fontVariantNumeric: 'tabular-nums' }}>{m}:{s} · est. ~10 min</span>
        )}
      </div>
    </div>
  )
}
