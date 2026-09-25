export type PixelCrop = {
    x: number;
    y: number;
    width: number;
    height: number;
};

/**
 * Get the size of the smallest box that holds an image rotated by the given degrees.
 */
export function rotatedBoundingBox(
    width: number,
    height: number,
    rotation: number,
): { width: number; height: number } {
    const radians = (rotation * Math.PI) / 180;

    return {
        width:
            Math.abs(Math.cos(radians) * width) +
            Math.abs(Math.sin(radians) * height),
        height:
            Math.abs(Math.sin(radians) * width) +
            Math.abs(Math.cos(radians) * height),
    };
}

/**
 * Scale a size down so its longest side fits within the limit, keeping the proportions.
 * A size that already fits is returned unchanged; the image is never enlarged.
 */
export function fitWithin(
    width: number,
    height: number,
    maxSide: number,
): { width: number; height: number } {
    const longestSide = Math.max(width, height);

    if (longestSide <= maxSide) {
        return { width: Math.round(width), height: Math.round(height) };
    }

    const scale = maxSide / longestSide;

    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}

/**
 * Keep a rotation within -180 (exclusive) and 180 (inclusive) degrees.
 */
export function normalizeRotation(rotation: number): number {
    const wrapped = ((((rotation + 180) % 360) + 360) % 360) - 180;

    return wrapped === -180 ? 180 : wrapped;
}

function loadImage(source: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();

        image.onload = () => resolve(image);
        image.onerror = () =>
            reject(new Error('No se pudo leer la imagen seleccionada.'));
        image.src = source;
    });
}

/**
 * Rotate the image, cut the selected area and shrink it to fit the limit, returning a PNG.
 */
export async function cropImage(
    source: string,
    crop: PixelCrop,
    rotation: number,
    maxSide: number,
): Promise<Blob> {
    const image = await loadImage(source);
    const box = rotatedBoundingBox(image.width, image.height, rotation);

    const rotated = document.createElement('canvas');
    rotated.width = Math.round(box.width);
    rotated.height = Math.round(box.height);

    const rotatedContext = rotated.getContext('2d');

    if (!rotatedContext) {
        throw new Error('Tu navegador no permite editar imágenes.');
    }

    rotatedContext.translate(rotated.width / 2, rotated.height / 2);
    rotatedContext.rotate((rotation * Math.PI) / 180);
    rotatedContext.translate(-image.width / 2, -image.height / 2);
    rotatedContext.drawImage(image, 0, 0);

    const size = fitWithin(crop.width, crop.height, maxSide);

    const output = document.createElement('canvas');
    output.width = size.width;
    output.height = size.height;

    const outputContext = output.getContext('2d');

    if (!outputContext) {
        throw new Error('Tu navegador no permite editar imágenes.');
    }

    outputContext.imageSmoothingQuality = 'high';
    outputContext.drawImage(
        rotated,
        crop.x,
        crop.y,
        crop.width,
        crop.height,
        0,
        0,
        size.width,
        size.height,
    );

    return new Promise((resolve, reject) => {
        output.toBlob(
            (blob) =>
                blob
                    ? resolve(blob)
                    : reject(new Error('No se pudo generar la imagen.')),
            'image/png',
        );
    });
}
