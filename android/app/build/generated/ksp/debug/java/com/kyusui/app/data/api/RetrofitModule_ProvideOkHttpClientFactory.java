package com.kyusui.app.data.api;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;
import okhttp3.OkHttpClient;

@ScopeMetadata("javax.inject.Singleton")
@QualifierMetadata
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
public final class RetrofitModule_ProvideOkHttpClientFactory implements Factory<OkHttpClient> {
  private final Provider<AuthInterceptor> authInterceptorProvider;

  private final Provider<ApiErrorParser> apiErrorParserProvider;

  public RetrofitModule_ProvideOkHttpClientFactory(
      Provider<AuthInterceptor> authInterceptorProvider,
      Provider<ApiErrorParser> apiErrorParserProvider) {
    this.authInterceptorProvider = authInterceptorProvider;
    this.apiErrorParserProvider = apiErrorParserProvider;
  }

  @Override
  public OkHttpClient get() {
    return provideOkHttpClient(authInterceptorProvider.get(), apiErrorParserProvider.get());
  }

  public static RetrofitModule_ProvideOkHttpClientFactory create(
      Provider<AuthInterceptor> authInterceptorProvider,
      Provider<ApiErrorParser> apiErrorParserProvider) {
    return new RetrofitModule_ProvideOkHttpClientFactory(authInterceptorProvider, apiErrorParserProvider);
  }

  public static OkHttpClient provideOkHttpClient(AuthInterceptor authInterceptor,
      ApiErrorParser apiErrorParser) {
    return Preconditions.checkNotNullFromProvides(RetrofitModule.INSTANCE.provideOkHttpClient(authInterceptor, apiErrorParser));
  }
}
