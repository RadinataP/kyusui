package com.kyusui.app.data.api;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;
import kotlinx.serialization.json.Json;

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
public final class RetrofitModule_ProvideApiErrorParserFactory implements Factory<ApiErrorParser> {
  private final Provider<Json> jsonProvider;

  public RetrofitModule_ProvideApiErrorParserFactory(Provider<Json> jsonProvider) {
    this.jsonProvider = jsonProvider;
  }

  @Override
  public ApiErrorParser get() {
    return provideApiErrorParser(jsonProvider.get());
  }

  public static RetrofitModule_ProvideApiErrorParserFactory create(Provider<Json> jsonProvider) {
    return new RetrofitModule_ProvideApiErrorParserFactory(jsonProvider);
  }

  public static ApiErrorParser provideApiErrorParser(Json json) {
    return Preconditions.checkNotNullFromProvides(RetrofitModule.INSTANCE.provideApiErrorParser(json));
  }
}
