const STAGE_HUE_PALETTE = [208, 162, 282, 24, 336, 124, 52, 248, 14, 96, 188, 306];
const FALLBACK_STAGE_HUE = 208;

const normalizeSeed = (stageId, stageName = '') => {
    if (stageId !== null && stageId !== undefined && String(stageId).trim() !== '') {
        return `id:${String(stageId).trim()}`;
    }

    if (typeof stageName === 'string' && stageName.trim() !== '') {
        return `name:${stageName.trim().toLowerCase()}`;
    }

    return '';
};

const hashSeed = (seed) => {
    let hash = 0;
    for (let i = 0; i < seed.length; i += 1) {
        hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash);
};

export const stageHue = (stageId, stageName = '') => {
    const seed = normalizeSeed(stageId, stageName);
    if (!seed) return FALLBACK_STAGE_HUE;

    const idx = hashSeed(seed) % STAGE_HUE_PALETTE.length;
    return STAGE_HUE_PALETTE[idx] ?? FALLBACK_STAGE_HUE;
};

export const stageAccentStyle = (stageId, stageName = '') => ({
    '--stage-h': String(stageHue(stageId, stageName)),
});

