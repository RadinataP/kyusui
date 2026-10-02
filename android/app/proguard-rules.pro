# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.
#
# For more details, see
#   http://developer.android.com/guide/developing/tools/proguard.html

# If your project uses WebView with JS, uncomment the following
# and specify the fully qualified class name to the JavaScript interface
# class:
#-keepclassmembers class fqcn.of.javascript.interface.for.webview {
#   public *;
#}

# Uncomment this to preserve the line number information for
# debugging stack traces.
#-keepattributes SourceFile,LineNumberTable

# If you keep the line number information, uncomment this to
# hide the original source file name.
#-renamesourcefileattribute SourceFile

# Hilt
-keep class dagger.hilt.** { *; }
-keep class * extends dagger.hilt.android.HiltAndroidApp
-keep class * extends dagger.hilt.android.HiltApplication
-keep class * extends dagger.hilt.android.HiltFragment
-keep class * extends dagger.hilt.android.HiltViewModel

# Kotlinx Serialization
-keep class kotlinx.serialization.** { *; }
-keepclassmembers class * {
    @kotlinx.serialization.Serializable *;
}

# Retrofit
-dontwarn retrofit2.**
-keep class retrofit2.** { *; }
-keepattributes Signature
-keepattributes Exceptions

# OkHttp
-dontwarn okhttp3.**
-keep class okhttp3.** { *; }

# Okio
-dontwarn okio.**
-keep class okio.** { *; }

# Kotlin Coroutines
-keep class kotlinx.coroutines.** { *; }

# Kotlinx Datetime
-keep class kotlinx.datetime.** { *; }

# Coil
-keep class coil.** { *; }

# Material 3
-keep class androidx.compose.material3.** { *; }

# Navigation
-keep class androidx.navigation.** { *; }

# DataStore
-keep class androidx.datastore.** { *; }

# Firebase
-keep class com.google.firebase.** { *; }

# Google Maps
-keep class com.google.android.gms.maps.** { *; }

# Location
-keep class com.google.android.gms.location.** { *; }

# Room (if used)
#-keep class androidx.room.** { *; }

# Hilt generated code
-keep class * extends dagger.hilt.android.internal.** { *; }