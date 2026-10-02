package com.kyusui.app.data.api;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;
import retrofit2.Retrofit;

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
public final class RetrofitModule_ProvideCustomerApiFactory implements Factory<CustomerApi> {
  private final Provider<Retrofit> retrofitProvider;

  public RetrofitModule_ProvideCustomerApiFactory(Provider<Retrofit> retrofitProvider) {
    this.retrofitProvider = retrofitProvider;
  }

  @Override
  public CustomerApi get() {
    return provideCustomerApi(retrofitProvider.get());
  }

  public static RetrofitModule_ProvideCustomerApiFactory create(
      Provider<Retrofit> retrofitProvider) {
    return new RetrofitModule_ProvideCustomerApiFactory(retrofitProvider);
  }

  public static CustomerApi provideCustomerApi(Retrofit retrofit) {
    return Preconditions.checkNotNullFromProvides(RetrofitModule.INSTANCE.provideCustomerApi(retrofit));
  }
}
