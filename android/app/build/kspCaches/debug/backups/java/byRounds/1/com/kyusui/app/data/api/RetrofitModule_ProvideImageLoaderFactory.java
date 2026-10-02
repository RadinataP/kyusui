package com.kyusui.app.data.api;

import android.content.Context;
import coil.ImageLoader;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;
import okhttp3.OkHttpClient;

@ScopeMetadata("javax.inject.Singleton")
@QualifierMetadata("dagger.hilt.android.qualifiers.ApplicationContext")
@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava"
})
public final class RetrofitModule_ProvideImageLoaderFactory implements Factory<ImageLoader> {
  private final Provider<Context> contextProvider;

  private final Provider<OkHttpClient> okHttpClientProvider;

  public RetrofitModule_ProvideImageLoaderFactory(Provider<Context> contextProvider,
      Provider<OkHttpClient> okHttpClientProvider) {
    this.contextProvider = contextProvider;
    this.okHttpClientProvider = okHttpClientProvider;
  }

  @Override
  public ImageLoader get() {
    return provideImageLoader(contextProvider.get(), okHttpClientProvider.get());
  }

  public static RetrofitModule_ProvideImageLoaderFactory create(Provider<Context> contextProvider,
      Provider<OkHttpClient> okHttpClientProvider) {
    return new RetrofitModule_ProvideImageLoaderFactory(contextProvider, okHttpClientProvider);
  }

  public static ImageLoader provideImageLoader(Context context, OkHttpClient okHttpClient) {
    return Preconditions.checkNotNullFromProvides(RetrofitModule.INSTANCE.provideImageLoader(context, okHttpClient));
  }
}
