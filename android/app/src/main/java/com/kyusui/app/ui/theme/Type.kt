package com.kyusui.app.ui.theme

import androidx.compose.material3.ColorScheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color

val LocalKyusuiColorScheme = staticCompositionLocalOf<ColorScheme> {
    KyusuiLightColorScheme
}

@Composable
fun KyusuiTheme(
    darkTheme: Boolean = false,
    colorScheme: ColorScheme = if (darkTheme) KyusuiDarkColorScheme else KyusuiLightColorScheme,
    content: @Composable () -> Unit
) {
    CompositionLocalProvider(
        LocalKyusuiColorScheme provides colorScheme
    ) {
        MaterialTheme(
            colorScheme = colorScheme,
            typography = Typography,
            shapes = Shapes,
            content = content
        )
    }
}

object KyusuiThemeAccessors {

    val colorScheme: ColorScheme
        @Composable
        @ReadOnlyComposable
        get() = LocalKyusuiColorScheme.current

    val colorPalette: KyusuiColorPalette
        @Composable
        @ReadOnlyComposable
        get() = KyusuiColorPalette(LocalKyusuiColorScheme.current)

    val shapes: Shapes
        @Composable
        @ReadOnlyComposable
        get() = MaterialTheme.shapes

    val typography: Typography
        @Composable
        @ReadOnlyComposable
        get() = MaterialTheme.typography
}

class KyusuiColorPalette(private val colorScheme: ColorScheme) {
    val primary: Color get() = colorScheme.primary
    val onPrimary: Color get() = colorScheme.onPrimary
    val primaryContainer: Color get() = colorScheme.primaryContainer
    val onPrimaryContainer: Color get() = colorScheme.onPrimaryContainer
    val secondary: Color get() = colorScheme.secondary
    val onSecondary: Color get() = colorScheme.onSecondary
    val secondaryContainer: Color get() = colorScheme.secondaryContainer
    val onSecondaryContainer: Color get() = colorScheme.onSecondaryContainer
    val tertiary: Color get() = colorScheme.tertiary
    val onTertiary: Color get() = colorScheme.onTertiary
    val tertiaryContainer: Color get() = colorScheme.tertiaryContainer
    val onTertiaryContainer: Color get() = colorScheme.onTertiaryContainer
    val error: Color get() = colorScheme.error
    val onError: Color get() = colorScheme.onError
    val errorContainer: Color get() = colorScheme.errorContainer
    val onErrorContainer: Color get() = colorScheme.onErrorContainer
    val surface: Color get() = colorScheme.surface
    val onSurface: Color get() = colorScheme.onSurface
    val surfaceVariant: Color get() = colorScheme.surfaceVariant
    val onSurfaceVariant: Color get() = colorScheme.onSurfaceVariant
    val outline: Color get() = colorScheme.outline
    val outlineVariant: Color get() = colorScheme.outlineVariant
    val scrim: Color get() = colorScheme.scrim
    val inverseSurface: Color get() = colorScheme.inverseSurface
    val onInverseSurface: Color get() = colorScheme.inverseOnSurface
    val inversePrimary: Color get() = colorScheme.inversePrimary
    val surfaceTint: Color get() = colorScheme.surfaceTint
}
