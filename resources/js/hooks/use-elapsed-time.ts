import { useEffect, useState } from 'react';

function formatearDuracion(segundos: number): string {
    const horas = Math.floor(segundos / 3600);
    const minutos = Math.floor((segundos % 3600) / 60);
    const restantes = segundos % 60;

    return [horas, minutos, restantes]
        .map((parte) => parte.toString().padStart(2, '0'))
        .join(':');
}

/**
 * Tiempo transcurrido desde `desde`, como "HH:MM:SS", actualizado cada segundo. No
 * depende de ninguna librería: un intervalo simple basta para un cronómetro en vivo.
 */
export function useElapsedTime(desde: Date): string {
    const [ahora, setAhora] = useState(() => new Date());

    useEffect(() => {
        const intervalo = setInterval(() => setAhora(new Date()), 1000);

        return () => clearInterval(intervalo);
    }, []);

    const segundos = Math.max(
        0,
        Math.floor((ahora.getTime() - desde.getTime()) / 1000),
    );

    return formatearDuracion(segundos);
}
