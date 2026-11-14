type Particle = {
    x: number;
    y: number;
    vx: number;
    vy: number;
    width: number;
    height: number;
    rotation: number;
    spin: number;
    color: string;
    life: number;
    maxLife: number;
};

const colors = [
    '#f43f5e',
    '#f59e0b',
    '#22c55e',
    '#38bdf8',
    '#a78bfa',
    '#fb7185',
];

let canvas: HTMLCanvasElement | null = null;
let context: CanvasRenderingContext2D | null = null;
let particles: Particle[] = [];
let frame = 0;

function resize(): void {
    if (!canvas || !context) return;

    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.floor(window.innerWidth * ratio);
    canvas.height = Math.floor(window.innerHeight * ratio);
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
}

function ensureCanvas(): void {
    if (canvas) return;

    canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.position = 'fixed';
    canvas.style.inset = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = '60';
    document.body.appendChild(canvas);
    context = canvas.getContext('2d');
    resize();
    window.addEventListener('resize', resize);
}

function clearCanvas(): void {
    if (!canvas) return;

    window.removeEventListener('resize', resize);
    canvas.remove();
    canvas = null;
    context = null;
    frame = 0;
}

function tick(): void {
    if (!context) return;

    const width = window.innerWidth;
    const height = window.innerHeight;
    context.clearRect(0, 0, width, height);

    const alive: Particle[] = [];

    for (const particle of particles) {
        particle.life += 1;
        particle.vy += 0.08;
        particle.x += particle.vx;
        particle.y += particle.vy;
        particle.rotation += particle.spin;

        if (particle.life >= particle.maxLife || particle.y > height + 24) {
            continue;
        }

        context.save();
        context.translate(particle.x, particle.y);
        context.rotate(particle.rotation);
        context.globalAlpha = 1 - particle.life / particle.maxLife;
        context.fillStyle = particle.color;
        context.fillRect(
            -particle.width / 2,
            -particle.height / 2,
            particle.width,
            particle.height,
        );
        context.restore();
        alive.push(particle);
    }

    particles = alive;

    if (particles.length === 0) {
        clearCanvas();
        return;
    }

    frame = window.requestAnimationFrame(tick);
}

export function dropConfetti(): void {
    if (typeof document === 'undefined') return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    ensureCanvas();

    const count = 150;

    for (let index = 0; index < count; index++) {
        particles.push({
            x: Math.random() * window.innerWidth,
            y: -10 - Math.random() * 24,
            vx: (Math.random() - 0.5) * 1.6,
            vy: 1.6 + Math.random() * 2.6,
            width: 6 + Math.random() * 4,
            height: 8 + Math.random() * 6,
            rotation: Math.random() * Math.PI,
            spin: (Math.random() - 0.5) * 0.2,
            color: colors[index % colors.length],
            life: 0,
            maxLife: 110 + Math.floor(Math.random() * 40),
        });
    }

    if (frame === 0) {
        frame = window.requestAnimationFrame(tick);
    }
}

type Spark = {
    x: number;
    y: number;
    vx: number;
    vy: number;
    color: string;
    life: number;
    maxLife: number;
};

export function dropFireworks(): void {
    if (typeof document === 'undefined') return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const show = document.createElement('canvas');
    show.setAttribute('aria-hidden', 'true');
    show.style.position = 'fixed';
    show.style.inset = '0';
    show.style.width = '100%';
    show.style.height = '100%';
    show.style.pointerEvents = 'none';
    show.style.zIndex = '60';
    document.body.appendChild(show);

    const ctx = show.getContext('2d');
    if (!ctx) {
        show.remove();
        return;
    }

    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    show.width = Math.floor(window.innerWidth * ratio);
    show.height = Math.floor(window.innerHeight * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

    const width = window.innerWidth;
    const height = window.innerHeight;
    const rockets: Spark[] = [];
    const sparks: Spark[] = [];
    let frames = 0;

    const launch = (): void => {
        rockets.push({
            x: width * (0.08 + Math.random() * 0.84),
            y: height + 8,
            vx: (Math.random() - 0.5) * 1.5,
            vy: -(height / 90 + Math.random() * 2),
            color: '#ffd166',
            life: 0,
            maxLife: 1000,
        });
    };

    launch();
    window.setTimeout(launch, 300);
    window.setTimeout(launch, 600);
    window.setTimeout(launch, 900);

    const explode = (rocket: Spark): void => {
        const count = 46 + Math.floor(Math.random() * 20);
        for (let index = 0; index < count; index++) {
            const angle = (index / count) * Math.PI * 2 + Math.random() * 0.3;
            const speed = 1.4 + Math.random() * 3.6;
            sparks.push({
                x: rocket.x,
                y: rocket.y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                color: colors[Math.floor(Math.random() * colors.length)],
                life: 0,
                maxLife: 55 + Math.floor(Math.random() * 30),
            });
        }
    };

    const step = (): void => {
        frames += 1;
        ctx.clearRect(0, 0, width, height);

        for (let index = rockets.length - 1; index >= 0; index--) {
            const rocket = rockets[index];
            rocket.vy += 0.12;
            rocket.x += rocket.vx;
            rocket.y += rocket.vy;
            ctx.fillStyle = rocket.color;
            ctx.beginPath();
            ctx.arc(rocket.x, rocket.y, 2, 0, Math.PI * 2);
            ctx.fill();
            if (rocket.vy >= -2.2) {
                explode(rocket);
                rockets.splice(index, 1);
            }
        }

        for (let index = sparks.length - 1; index >= 0; index--) {
            const spark = sparks[index];
            spark.life += 1;
            spark.vx *= 0.985;
            spark.vy = spark.vy * 0.985 + 0.05;
            spark.x += spark.vx;
            spark.y += spark.vy;
            ctx.globalAlpha = Math.max(0, 1 - spark.life / spark.maxLife);
            ctx.fillStyle = spark.color;
            ctx.beginPath();
            ctx.arc(spark.x, spark.y, 1.6, 0, Math.PI * 2);
            ctx.fill();
            ctx.globalAlpha = 1;
            if (spark.life >= spark.maxLife) {
                sparks.splice(index, 1);
            }
        }

        if (rockets.length > 0 || sparks.length > 0 || frames < 280) {
            window.requestAnimationFrame(step);
        } else {
            show.remove();
        }
    };

    window.requestAnimationFrame(step);
}
